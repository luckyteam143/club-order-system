<?php

namespace App\Imports;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Deliberately NOT WithChunkReading: sheets exported/edited in Excel or
 * LibreOffice often carry a bogus "used range" that extends to the sheet's
 * max row (e.g. from formatting whole columns) even though real data is a
 * few hundred/thousand rows. Chunked reading re-parses the whole workbook
 * (styles, shared strings, etc.) per chunk against that inflated row count,
 * which can take minutes per chunk and never finish. A single unchunked
 * read doesn't have that problem — verified at ~1s / ~55MB for a ~1,000 row
 * product sheet, well within the 512M/600s this action already grants
 * itself in ListProducts.
 */
class ProductsImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            $this->importChunk($rows);
        });
    }

    protected function importChunk(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $data = [
                'barcode'               => $this->nullableString($row['barcode'] ?? null),
                'parent_sku'            => $this->nullableString($row['parent_sku'] ?? null),
                'default_sku'           => $this->nullableString($row['default_sku'] ?? null),
                'size'                  => $this->nullableString($row['size'] ?? null),
                'name'                  => $name,
                'status'                => $this->nullableString($row['status'] ?? null) ?? 'Active',
                'macro_category'        => $this->nullableString($row['macro_category'] ?? null),
                'description'           => $this->nullableString($row['product_description'] ?? $row['description'] ?? null),
                'qty'                   => (int) ($row['qty'] ?? 0),
                'on_backorder'          => in_array(strtolower((string) ($row['on_backorder'] ?? '')), ['yes', '1', 'true'], true),
                'backorder_date'        => $this->nullableString($row['backorder_date'] ?? null),
                'retail_price'          => (float) ($row['retail_price'] ?? 0),
                'image_link'            => $this->nullableString($row['main_image'] ?? $row['image_link'] ?? null),
                'gallery_links'         => $this->nullableString($row['gallery_links'] ?? null),
                'year'                  => $this->nullableString($row['year'] ?? null),
                'available_until_year'  => $this->nullableString($row['available_until_year'] ?? null),
                'total_look'            => $this->nullableString($row['total_look'] ?? null),
                'weight'                => isset($row['weight']) && $row['weight'] !== '' ? (float) $row['weight'] : null,
                'color1_code'           => $this->nullableString($row['color1_code'] ?? null),
                'color1_label'          => $this->nullableString($row['color1_label'] ?? null),
                'color2_code'           => $this->nullableString($row['color2_code'] ?? null),
                'color2_label'          => $this->nullableString($row['color2_label'] ?? null),
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
