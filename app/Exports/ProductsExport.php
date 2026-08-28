<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    /** @var Collection<int, Collection> product_id => attribute name rows, smallest-to-largest by position */
    private Collection $attributeNamesByProductId;

    /**
     * @param  array<int>|null  $productIds  When given, only these products are
     *                                       exported (the "export selected rows"
     *                                       bulk action). Null exports the whole
     *                                       catalog.
     */
    public function __construct(private ?array $productIds = null)
    {
    }

    public function title(): string
    {
        return 'Products';
    }

    public function headings(): array
    {
        return [
            'ID', 'Barcode', 'Parent SKU', 'Default SKU', 'Size', 'Name',
            'Status', 'Macro Category', 'Product Description',
            'Qty', 'On Backorder', 'Backorder Date', 'Retail Price', 'Attributes',
            'Main Image', 'Gallery Links', 'Year', 'Available Until Year', 'Total Look',
            'Weight', 'Color1 Code', 'Color1 Label', 'Color2 Code', 'Color2 Label',
        ];
    }

    public function collection(): Collection
    {
        // Product::with('attributes') hydrates a full Eloquent model +
        // Pivot instance for every one of the catalog's ~60k
        // attribute_product rows across ~30k products — that's what was
        // exhausting the 256M memory limit in production. Pull the
        // attribute names through the query builder instead (lightweight
        // stdClass rows), same technique already used by
        // OrderResource::productsCatalogForGrid() for the same reason.
        $this->attributeNamesByProductId = DB::table('attribute_product')
            ->join('attributes', 'attributes.id', '=', 'attribute_product.attribute_id')
            ->select(['attribute_product.product_id', 'attributes.name'])
            ->when($this->productIds !== null, fn ($query) => $query->whereIn('attribute_product.product_id', $this->productIds))
            ->orderBy('attributes.position')
            ->orderBy('attributes.name')
            ->get()
            ->groupBy('product_id');

        return Product::query()
            ->when($this->productIds !== null, fn ($query) => $query->whereIn('id', $this->productIds))
            ->orderBy('name')
            ->get();
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->barcode,
            $product->parent_sku,
            $product->default_sku,
            $product->size,
            $product->name,
            $product->status,
            $product->macro_category,
            $product->description,
            $product->qty,
            $product->on_backorder ? 'Yes' : 'No',
            optional($product->backorder_date)->format('Y-m-d'),
            $product->retail_price,
            ($this->attributeNamesByProductId->get($product->id) ?? collect())->pluck('name')->implode(', '),
            $product->image_link,
            $product->gallery_links,
            $product->year,
            $product->available_until_year,
            $product->total_look,
            $product->weight,
            $product->color1_code,
            $product->color1_label,
            $product->color2_code,
            $product->color2_label,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
