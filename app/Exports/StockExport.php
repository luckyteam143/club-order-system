<?php

namespace App\Exports;

use App\Models\ProductWarehouseStock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'Stock';
    }

    public function headings(): array
    {
        return ['Product ID', 'Barcode', 'Product Name', 'Warehouse', 'Qty'];
    }

    public function collection(): Collection
    {
        return ProductWarehouseStock::with(['product', 'warehouse'])
            ->join('products', 'products.id', '=', 'product_warehouse_stock.product_id')
            ->orderBy('products.name')
            ->select('product_warehouse_stock.*')
            ->get();
    }

    public function map($stock): array
    {
        return [
            $stock->product_id,
            $stock->product?->barcode,
            $stock->product?->name,
            $stock->warehouse?->name,
            $stock->qty,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
