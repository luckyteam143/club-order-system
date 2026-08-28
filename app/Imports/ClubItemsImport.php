<?php

namespace App\Imports;

use App\Models\Club;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Round-trips ClubItemsExport: ID, Barcode, Name, Club Price, Online Store Price.
 *
 * Non-destructive — items already on the club that aren't in the file are
 * left alone; nothing is deleted.
 *
 *  - Row WITH an ID  -> update that club item's two prices (the ID is the
 *    club_product row from the export; Barcode/Name are ignored).
 *  - Row WITHOUT an ID -> matched by Barcode: the product is attached if it
 *    isn't already on the club, otherwise its prices are updated.
 *
 * Blank price cell = cleared (null).
 */
class ClubItemsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $imported = 0;

    /** @var list<string> */
    public array $errors = [];

    public function __construct(private Club $club)
    {
    }

    public function collection(Collection $rows): void
    {
        $sync = [];

        foreach ($rows as $i => $row) {
            $row = collect($row);
            // +1 for the heading row, +1 because spreadsheet rows are 1-based.
            $line = $i + 2;
            $id = trim((string) ($row['id'] ?? ''));

            if ($id !== '') {
                $pivot = DB::table('club_product')
                    ->where('id', (int) $id)
                    ->where('club_id', $this->club->id)
                    ->first();

                if (! $pivot) {
                    $this->errors[] = "Row {$line}: no club item with ID {$id}.";

                    continue;
                }

                DB::table('club_product')->where('id', $pivot->id)->update([
                    'club_price'         => $this->price($row['club_price'] ?? null),
                    'online_store_price' => $this->price($row['online_store_price'] ?? null),
                    'updated_at'         => now(),
                ] + $this->crestColumns($row));

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
                $this->errors[] = "Row {$line}: \"{$product->name}\" is a size variant — only parent products can be assigned to a club.";

                continue;
            }

            $sync[$product->id] = [
                'club_price'         => $this->price($row['club_price'] ?? null),
                'online_store_price' => $this->price($row['online_store_price'] ?? null),
            ] + $this->crestColumns($row);
        }

        if ($sync) {
            // sync(..., detaching: false) — adds new items and updates the
            // pivot prices on ones already attached, removes nothing.
            $this->club->products()->syncWithoutDetaching($sync);
            $this->imported += count($sync);
        }
    }

    private function price($value): ?float
    {
        $value = str_replace(',', '', ltrim(trim((string) $value), '$'));

        return $value === '' ? null : (float) $value;
    }

    /**
     * The crest pivot columns to write for a row — only when the file
     * actually carries a crest column, so re-importing an older 5-column
     * export leaves existing crest settings untouched. Blank "Has Club
     * Crest" defaults to Yes; blank / zero "Crest Number" defaults to 1.
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
            'crest_number'   => $hasCrest && $number > 0 ? $number : 1,
        ];
    }
}
