<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
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
        return Product::with('attributes')->orderBy('name')->get();
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
            $product->attributes->pluck('name')->implode(', '),
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
