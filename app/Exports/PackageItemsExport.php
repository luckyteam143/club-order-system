<?php

namespace App\Exports;

use App\Models\Package;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One package's item rows (the package_product pivot), in display order.
 * A product listed more than once in the package exports as that many
 * rows. Round-trips with PackageItemsImport. An empty package still
 * exports the header row, so this doubles as the import template.
 */
class PackageItemsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private Package $package)
    {
    }

    public function title(): string
    {
        return 'Package Items';
    }

    public function headings(): array
    {
        return ['ID', 'Barcode', 'Name', 'Qty', 'Price Override', 'Has Club Crest', 'Crest Number'];
    }

    public function collection(): Collection
    {
        return $this->package->packageProducts()
            ->with('product:id,barcode,name')
            ->orderBy('sort_order')
            ->get();
    }

    public function map($packageProduct): array
    {
        return [
            $packageProduct->id,
            $packageProduct->product?->barcode,
            $packageProduct->product?->name,
            $packageProduct->qty,
            $packageProduct->per_item_price,
            $packageProduct->has_club_crest ? 'Yes' : 'No',
            $packageProduct->crest_number ?? 1,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
