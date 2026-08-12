<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Long-format stock import: one row per product x warehouse, matching
 * StockExport's own layout so a round-trip export -> edit -> import works.
 * Columns: Product ID (optional if Barcode given), Barcode, Product Name
 * (ignored — for reference only), Warehouse (name or code), Qty.
 */
class StockImport implements SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    use SkipsFailures;

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
                $warehouse = $this->resolveWarehouse($row);

                if (! $product || ! $warehouse) {
                    continue;
                }

                $qty = max(0, (int) ($row['qty'] ?? 0));

                ProductWarehouseStock::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['qty' => $qty],
                );

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

    protected function resolveWarehouse(Collection $row): ?Warehouse
    {
        $name = trim((string) ($row['warehouse'] ?? ''));

        if ($name === '') {
            return null;
        }

        return Warehouse::where('name', $name)
            ->orWhere('code', $name)
            ->first();
    }

    public function rules(): array
    {
        return [
            'warehouse' => 'required|string',
            'qty'       => 'nullable|numeric',
        ];
    }
}
