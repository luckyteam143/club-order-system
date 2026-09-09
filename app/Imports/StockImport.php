<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Matrix-format stock import: one row per product, one column per
 * warehouse — matching StockExport's own layout (and the Stock listing's
 * on-screen grid) so a round-trip export -> edit -> import works.
 * Columns: Product ID (optional if Barcode given), Barcode, Product Name /
 * Size / Total Look (ignored — for reference only), then one qty column per
 * active warehouse named after it. On Hold (all) and Total are read-only
 * reference columns and are never imported — reserve qty is managed via the
 * Scan Stock feature instead.
 *
 * Only warehouse columns actually present in the sheet are written — as
 * with ProductsImport, omitting a warehouse's column leaves its stock
 * untouched rather than zeroing it out.
 */
class StockImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    /** @var Collection<int, Warehouse> */
    private Collection $warehouses;

    public function __construct()
    {
        $this->warehouses = Warehouse::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            $touchedProductIds = [];

            foreach ($rows as $row) {
                $product = $this->resolveProduct($row);

                if (! $product) {
                    continue;
                }

                foreach ($this->warehouses as $warehouse) {
                    $key = Str::slug($warehouse->name, '_');

                    if (! $row->has($key) || $row[$key] === null || $row[$key] === '') {
                        continue;
                    }

                    ProductWarehouseStock::applyQty($product->id, $warehouse->id, (int) $row[$key]);
                }

                $touchedProductIds[$product->id] = true;
            }

            Product::whereIn('id', array_keys($touchedProductIds))
                ->get()
                ->each(fn (Product $product) => $product->recalculateStock());
        });
    }

    protected function resolveProduct(Collection $row): ?Product
    {
        if (! empty($row['product_id'])) {
            $product = Product::find((int) $row['product_id']);

            if ($product) {
                return $product;
            }
        }

        $barcode = trim((string) ($row['barcode'] ?? ''));

        return $barcode === '' ? null : Product::where('barcode', $barcode)->first();
    }
}
