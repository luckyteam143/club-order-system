<?php

namespace App\Imports;

use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Package;
use App\Models\PackageProduct;
use App\Models\PackageProductEmbellishment;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Round-trips PackageEmbellishmentsExport: ID, Item Barcode, Item Name,
 * Embellishment, Position, Override Price.
 *
 * Non-destructive — embellishment rows not listed in the file are left
 * untouched; nothing is deleted by an import (remove them in the UI).
 *
 *  - Row WITH an ID     -> update that embellishment row (Embellishment,
 *    Position, Override Price). The ID is the package_product_embellishments
 *    row from the export; it must belong to this package.
 *  - Row WITHOUT an ID  -> added to the package item whose product matches
 *    "Item Barcode". An identical assignment that already exists (same item
 *    + embellishment + position) is skipped, so re-importing is safe.
 *
 * "Embellishment" and "Position" are matched by name (case-insensitive).
 * Blank Position / Override Price = none.
 */
class PackageEmbellishmentsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<string> */
    public array $errors = [];

    public function __construct(private Package $package) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'The file has no embellishment rows.';

            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $i => $row) {
                $row = collect($row);
                $line = $i + 2; // +1 heading row, +1 for 1-based rows

                $id = trim((string) ($row['id'] ?? ''));
                $embellishmentName = trim((string) ($row['embellishment'] ?? ''));
                $positionName = trim((string) ($row['position'] ?? ''));
                $price = $this->price($row['override_price'] ?? null);

                if ($embellishmentName === '') {
                    $this->errors[] = "Row {$line}: Embellishment is required.";

                    continue;
                }

                $embellishmentId = $this->resolveEmbellishment($embellishmentName, $line);
                if ($embellishmentId === null) {
                    continue;
                }

                $positionId = null;
                if ($positionName !== '') {
                    $positionId = $this->resolvePosition($positionName, $line);
                    if ($positionId === false) {
                        continue;
                    }
                }

                if ($id !== '') {
                    $embellishment = PackageProductEmbellishment::query()
                        ->whereKey((int) $id)
                        ->whereIn('package_product_id', $this->package->packageProducts()->select('id'))
                        ->first();

                    if (! $embellishment) {
                        $this->errors[] = "Row {$line}: no embellishment row with ID {$id} on this package.";

                        continue;
                    }

                    $embellishment->update([
                        'embellishment_id' => $embellishmentId,
                        'embellishment_position_id' => $positionId,
                        'override_price' => $price,
                    ]);
                    $this->imported++;

                    continue;
                }

                $packageProductId = $this->resolvePackageProductId(trim((string) ($row['item_barcode'] ?? '')), $line);
                if ($packageProductId === null) {
                    continue;
                }

                $exists = PackageProductEmbellishment::query()
                    ->where('package_product_id', $packageProductId)
                    ->where('embellishment_id', $embellishmentId)
                    ->where('embellishment_position_id', $positionId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                PackageProductEmbellishment::create([
                    'package_product_id' => $packageProductId,
                    'embellishment_id' => $embellishmentId,
                    'embellishment_position_id' => $positionId,
                    'override_price' => $price,
                ]);
                $this->imported++;
            }
        });
    }

    private function resolveEmbellishment(string $name, int $line): ?int
    {
        $matches = Embellishment::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->pluck('id');

        if ($matches->isEmpty()) {
            $this->errors[] = "Row {$line}: no embellishment named \"{$name}\".";

            return null;
        }

        if ($matches->count() > 1) {
            $this->errors[] = "Row {$line}: more than one embellishment named \"{$name}\" — rename one so it's unambiguous.";

            return null;
        }

        return (int) $matches->first();
    }

    /** @return int|false position id, or false when the name doesn't resolve */
    private function resolvePosition(string $name, int $line): int|false
    {
        $matches = EmbellishmentPosition::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->pluck('id');

        if ($matches->isEmpty()) {
            $this->errors[] = "Row {$line}: no position named \"{$name}\".";

            return false;
        }

        if ($matches->count() > 1) {
            $this->errors[] = "Row {$line}: more than one position named \"{$name}\".";

            return false;
        }

        return (int) $matches->first();
    }

    private function resolvePackageProductId(string $barcode, int $line): ?int
    {
        if ($barcode === '') {
            $this->errors[] = "Row {$line}: no ID and no Item Barcode.";

            return null;
        }

        $product = Product::where('barcode', $barcode)->first();

        if (! $product) {
            $this->errors[] = "Row {$line}: no product with barcode \"{$barcode}\".";

            return null;
        }

        // Assignments are tracked per product — when the same product is in
        // the package more than once, the last item row owns them (matches
        // PersistsPackageItems).
        $packageProductId = PackageProduct::query()
            ->where('package_id', $this->package->id)
            ->where('product_id', $product->id)
            ->orderByDesc('sort_order')
            ->value('id');

        if (! $packageProductId) {
            $this->errors[] = "Row {$line}: \"{$product->name}\" is not an item in this package.";

            return null;
        }

        return (int) $packageProductId;
    }

    private function price($value): ?float
    {
        $value = str_replace(',', '', ltrim(trim((string) $value), '$'));

        return $value === '' ? null : (float) $value;
    }
}
