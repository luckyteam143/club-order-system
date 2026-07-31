<?php

namespace App\Imports;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $data = [
                'barcode'        => $this->nullableString($row['barcode'] ?? null),
                'parent_sku'     => $this->nullableString($row['parent_sku'] ?? null),
                'default_sku'    => $this->nullableString($row['default_sku'] ?? null),
                'size'           => $this->nullableString($row['size'] ?? null),
                'name'           => $name,
                'qty'            => (int) ($row['qty'] ?? 0),
                'on_backorder'   => in_array(strtolower((string) ($row['on_backorder'] ?? '')), ['yes', '1', 'true'], true),
                'backorder_date' => $this->nullableString($row['backorder_date'] ?? null),
                'retail_price'   => (float) ($row['retail_price'] ?? 0),
            ];

            $product = null;

            if (! empty($row['id'])) {
                $product = Product::find((int) $row['id']);
            }

            if (! $product && $data['barcode']) {
                $product = Product::where('barcode', $data['barcode'])->first();
            }

            if ($product) {
                $product->update($data);
            } else {
                $product = Product::create($data);
            }

            $this->syncAttributes($product, $row['attributes'] ?? null);
        }
    }

    protected function syncAttributes(Product $product, $raw): void
    {
        if ($raw === null || trim((string) $raw) === '') {
            return;
        }

        $names = array_filter(array_map('trim', explode(',', (string) $raw)));

        $attributeIds = collect($names)->map(function (string $name) {
            $attribute = Attribute::where('name', $name)->first();

            if (! $attribute) {
                $sku = Str::upper(Str::slug($name, '-'));
                $uniqueSku = $sku;
                $suffix = 1;

                while (Attribute::where('sku', $uniqueSku)->exists()) {
                    $uniqueSku = $sku . '-' . (++$suffix);
                }

                $attribute = Attribute::create(['name' => $name, 'sku' => $uniqueSku]);
            }

            return $attribute->id;
        });

        $product->attributes()->sync($attributeIds);
    }

    protected function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'qty' => 'nullable|numeric',
            'retail_price' => 'nullable|numeric',
        ];
    }
}
