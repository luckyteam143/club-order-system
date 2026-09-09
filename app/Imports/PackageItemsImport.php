<?php

namespace App\Imports;

use App\Models\Package;
use App\Models\PackageProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Round-trips PackageItemsExport: ID, Barcode, Name, Qty, Price Override,
 * Has Club Crest, Crest Number, Goalkeeper Item, Player Item.
 *
 * Non-destructive — package items not listed in the file are left untouched;
 * nothing is deleted by an import (remove items from the package in the UI).
 *
 *  - Row WITH an ID  -> update that package item's Qty and Price Override
 *    (the ID is the package_product row from the export; Barcode/Name are
 *    ignored, sponsor/embellishment settings are kept).
 *  - Row WITHOUT an ID -> added as a new item at the end of the package,
 *    matched to a product by Barcode. The same product may be added on
 *    several ID-less rows (packages allow duplicate items).
 *
 * Blank Price Override = no override (null). Qty defaults to 1.
 */
class PackageItemsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<string> */
    public array $errors = [];

    public function __construct(private Package $package) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'The file has no item rows.';

            return;
        }

        $clubId = $this->package->club_id;
        $nextSort = ((int) $this->package->packageProducts()->max('sort_order')) + 1;

        DB::transaction(function () use ($rows, $clubId, &$nextSort) {
            foreach ($rows as $i => $row) {
                $row = collect($row);
                $line = $i + 2; // +1 heading row, +1 for 1-based rows

                $id = trim((string) ($row['id'] ?? ''));
                $qty = max(1, (int) ($row['qty'] ?? 1));
                $price = $this->price($row['price_override'] ?? null);

                if ($id !== '') {
                    $packageProduct = PackageProduct::query()
                        ->where('package_id', $this->package->id)
                        ->whereKey((int) $id)
                        ->first();

                    if (! $packageProduct) {
                        $this->errors[] = "Row {$line}: no package item with ID {$id}.";

                        continue;
                    }

                    $packageProduct->update(['qty' => $qty, 'per_item_price' => $price] + $this->crestColumns($row) + $this->goaliePlayerColumns($row) + $this->numberColorColumn($row));
                    $this->imported++;

                    continue;
                }

                $barcode = trim((string) ($row['barcode'] ?? ''));

                if ($barcode === '') {
                    $this->errors[] = "Row {$line}: no ID and no Barcode.";

                    continue;
                }

                $product = Product::where('barcode', $barcode)->first();

                if (! $product) {
                    $this->errors[] = "Row {$line}: no product with barcode \"{$barcode}\".";

                    continue;
                }

                if ($product->parent_sku !== null) {
                    $this->errors[] = "Row {$line}: \"{$product->name}\" is a size variant — only parent products can be in a package.";

                    continue;
                }

                if ($clubId && ! $product->clubs()->whereKey($clubId)->exists()) {
                    $this->errors[] = "Row {$line}: \"{$product->name}\" is not one of this package's club's items.";

                    continue;
                }

                PackageProduct::create([
                    'package_id' => $this->package->id,
                    'product_id' => $product->id,
                    'sort_order' => $nextSort++,
                    'qty' => $qty,
                    'per_item_price' => $price,
                ] + $this->crestColumns($row) + $this->goaliePlayerColumns($row) + $this->numberColorColumn($row));

                $this->imported++;
            }
        });
    }

    private function price($value): ?float
    {
        $value = str_replace(',', '', ltrim(trim((string) $value), '$'));

        return $value === '' ? null : (float) $value;
    }

    /**
     * The crest columns to write for a row — only when the file actually
     * carries a crest column, so re-importing an older 5-column export
     * leaves existing crest settings untouched. Blank "Has Club Crest"
     * defaults to Yes; blank / zero "Crest Number" defaults to 1.
     *
     * @return array{has_club_crest?: bool, crest_number?: int}
     */
    private function crestColumns(Collection $row): array
    {
        if (! $row->has('has_club_crest') && ! $row->has('crest_number')) {
            return [];
        }

        $flag = strtolower(trim((string) ($row['has_club_crest'] ?? '')));
        $hasCrest = $flag === '' ? true : in_array($flag, ['1', 'yes', 'y', 'true', 't'], true);

        $number = (int) trim((string) ($row['crest_number'] ?? ''));

        return [
            'has_club_crest' => $hasCrest,
            'crest_number' => $hasCrest && $number > 0 ? $number : 1,
        ];
    }

    /**
     * Same present-column guard as crestColumns() — only written when the
     * file actually carries a "Goalkeeper Item" / "Player Item" column, so
     * re-importing an older export leaves the existing flags untouched.
     * Blank cells default to No (goalkeeper) / Yes (player).
     *
     * @return array{is_goalie_item?: bool, is_player_item?: bool}
     */
    private function goaliePlayerColumns(Collection $row): array
    {
        if (! $row->has('goalkeeper_item') && ! $row->has('player_item')) {
            return [];
        }

        $yes = fn ($value) => in_array(strtolower(trim((string) $value)), ['1', 'yes', 'y', 'true', 't'], true);

        $goalieFlag = trim((string) ($row['goalkeeper_item'] ?? ''));
        $playerFlag = trim((string) ($row['player_item'] ?? ''));

        return [
            'is_goalie_item' => $goalieFlag === '' ? false : $yes($goalieFlag),
            'is_player_item' => $playerFlag === '' ? true : $yes($playerFlag),
        ];
    }

    /**
     * Only written when the file actually carries a "Number Colour" column,
     * so re-importing an older export leaves the existing value untouched.
     * A blank cell clears the colour.
     *
     * @return array{number_color?: string|null}
     */
    private function numberColorColumn(Collection $row): array
    {
        if (! $row->has('number_colour') && ! $row->has('number_color')) {
            return [];
        }

        $value = trim((string) ($row['number_colour'] ?? $row['number_color'] ?? ''));

        return ['number_color' => $value === '' ? null : $value];
    }
}
