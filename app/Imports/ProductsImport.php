<?php

namespace App\Imports;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Partial-column aware. Only the columns actually present in the uploaded
 * sheet are written; any product field whose column is absent keeps its
 * current database value untouched. So a "Barcode + Retail Price" sheet
 * updates only the price, while a full Export-Excel round-trip still updates
 * every field.
 *
 * Deliberately NOT WithChunkReading: sheets exported/edited in Excel or
 * LibreOffice often carry a bogus "used range" that extends to the sheet's
 * max row even though real data is a few hundred/thousand rows. Chunked
 * reading re-parses the whole workbook per chunk against that inflated row
 * count and can take minutes per chunk. A single unchunked read is ~1s /
 * ~55MB for a ~1,000 row sheet. SkipsEmptyRows drops the blank rows that the
 * same inflated range produces.
 */
class ProductsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /**
     * DB column => the heading key(s) that feed it. maatwebsite lower-cases
     * headings and turns spaces into underscores, so "Retail Price" arrives
     * as `retail_price`. First present key wins.
     */
    private const FIELD_SOURCES = [
        'barcode'              => ['barcode'],
        'parent_sku'           => ['parent_sku'],
        'default_sku'          => ['default_sku'],
        'size'                 => ['size'],
        'name'                 => ['name'],
        'status'               => ['status'],
        'macro_category'       => ['macro_category'],
        'description'          => ['product_description', 'description'],
        'qty'                  => ['qty'],
        'on_backorder'         => ['on_backorder'],
        'backorder_date'       => ['backorder_date'],
        'retail_price'         => ['retail_price'],
        'image_link'           => ['main_image', 'image_link'],
        'gallery_links'        => ['gallery_links'],
        'year'                 => ['year'],
        'available_until_year' => ['available_until_year'],
        'total_look'           => ['total_look'],
        'weight'               => ['weight'],
        'color1_code'          => ['color1_code'],
        'color1_label'         => ['color1_label'],
        'color2_code'          => ['color2_code'],
        'color2_label'         => ['color2_label'],
    ];

    public int $imported = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> heading keys found in the uploaded sheet */
    private array $headers = [];

    public function collection(Collection $rows): void
    {
        $first = $rows->first();

        if (! $first) {
            return;
        }

        $this->headers = array_keys($first->toArray());

        // The product fields this particular sheet carries.
        $fields = array_keys(array_filter(
            self::FIELD_SOURCES,
            fn (array $sources): bool => (bool) array_intersect($sources, $this->headers),
        ));

        $hasIdColumn = in_array('id', $this->headers, true);

        if (! $hasIdColumn && ! in_array('barcode', $fields, true)) {
            $this->errors[] = 'The sheet needs an ID or Barcode column so rows can be matched to products.';

            return;
        }

        $hasNameColumn = in_array('name', $fields, true);

        foreach ($rows->chunk(500) as $batch) {
            DB::transaction(fn () => $this->importChunk($batch, $fields, $hasNameColumn));

            gc_collect_cycles();
        }

        // The order grid's catalog reads are cached for a short window —
        // drop them now so a bulk import's new names/sizes/prices show up
        // on the next order page load instead of waiting out the TTL.
        \App\Filament\Resources\OrderResource::clearGridCatalogCache();
    }

    /**
     * @param  list<string>  $fields  product columns present in this sheet
     */
    protected function importChunk(Collection $rows, array $fields, bool $hasNameColumn): void
    {
        foreach ($rows as $i => $row) {
            // +1 heading row, +1 because spreadsheet rows are 1-based.
            $line = $i + 2;

            if ($hasNameColumn && trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }

            $data = [];

            foreach ($fields as $field) {
                $data[$field] = $this->transform($row, $field);
            }

            $product = null;

            if (! empty($row['id'])) {
                $product = Product::find((int) $row['id']);

                if (! $product) {
                    $this->errors[] = "Row {$line}: no product with ID {$row['id']}.";

                    continue;
                }
            }

            if (! $product && ! empty($data['barcode'])) {
                $product = Product::where('barcode', $data['barcode'])->first();
            }

            if ($product) {
                $product->update($data);
            } else {
                if (blank($data['name'] ?? null)) {
                    $this->errors[] = "Row {$line}: no existing product matched (by ID or Barcode) and no Name to create one.";

                    continue;
                }

                $product = Product::create($data);
            }

            $this->imported++;

            // Only touch attributes when the sheet carries that column; a
            // blank cell there still leaves the product's attributes as-is.
            if (in_array('attributes', $this->headers, true)) {
                $this->syncAttributes($product, $row['attributes'] ?? null);
            }
        }
    }

    /**
     * The value to write for one product column, read from whichever heading
     * key(s) feed it.
     */
    protected function transform(Collection $row, string $field): mixed
    {
        return match ($field) {
            'name'         => trim((string) ($row['name'] ?? '')),
            'status'       => $this->nullableString($row['status'] ?? null) ?? 'Active',
            'description'  => $this->nullableString($row['product_description'] ?? $row['description'] ?? null),
            'qty'          => (int) ($row['qty'] ?? 0),
            'on_backorder' => in_array(strtolower((string) ($row['on_backorder'] ?? '')), ['yes', '1', 'true'], true),
            'retail_price' => (float) ($row['retail_price'] ?? 0),
            'image_link'   => $this->nullableString($row['main_image'] ?? $row['image_link'] ?? null),
            'weight'       => isset($row['weight']) && $row['weight'] !== '' ? (float) $row['weight'] : null,
            default        => $this->nullableString($row[$field] ?? null),
        };
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
}
