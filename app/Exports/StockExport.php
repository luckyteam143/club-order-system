<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per product, one column per warehouse — mirrors the Stock
 * listing's matrix layout (StockResource::table) so the export, import and
 * on-screen grid all read the same way. StockImport parses this same shape
 * back in.
 */
class StockExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    /** @var Collection<int, Warehouse> */
    private Collection $warehouses;

    /**
     * @param  array<int>|null  $productIds  When given, only these products are
     *                                       exported (the "export selected rows"
     *                                       bulk action). Null exports every
     *                                       active product.
     */
    public function __construct(private ?array $productIds = null)
    {
        $this->warehouses = Warehouse::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    public function title(): string
    {
        return 'Stock';
    }

    public function headings(): array
    {
        return [
            'Product ID', 'Barcode', 'Product Name', 'Size', 'Total Look',
            ...$this->warehouses->pluck('name')->all(),
            'On Hold (all)', 'Total',
        ];
    }

    public function collection(): Collection
    {
        $query = Product::query()->where('status', 'Active')->with('warehouseStocks');

        if ($this->productIds !== null) {
            $query->whereIn('id', $this->productIds);
        }

        return $query->orderBy('name')->get();
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->barcode,
            $product->name,
            $product->size,
            $product->total_look,
            ...$this->warehouses->map(fn (Warehouse $warehouse) => $product->warehouseStocks->firstWhere('warehouse_id', $warehouse->id)?->qty ?? 0)->all(),
            (int) $product->warehouseStocks->sum('qty_on_hold'),
            $product->qty,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
