<?php

namespace App\Exports;

use App\Models\Package;
use App\Models\PackageProduct;
use App\Models\PackageProductEmbellishment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One package's embellishment assignments — one row per embellishment on
 * per package item, in item display order. Round-trips with
 * PackageEmbellishmentsImport. A package with no embellishments still
 * exports the header row, so this doubles as the import template.
 *
 * Embellishments attach to a package item by that item's product Barcode
 * (see PackageEmbellishmentsImport / PersistsPackageItems — assignments are
 * tracked per product, not per duplicate item row).
 */
class PackageEmbellishmentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private Package $package) {}

    public function title(): string
    {
        return 'Package Embellishments';
    }

    public function headings(): array
    {
        return ['ID', 'Item Barcode', 'Item Name', 'Embellishment', 'Position', 'Override Price'];
    }

    public function collection(): Collection
    {
        return $this->package->packageProducts()
            ->with(['product:id,barcode,name', 'embellishments.embellishment:id,name', 'embellishments.position:id,name'])
            ->orderBy('sort_order')
            ->get()
            ->flatMap(fn (PackageProduct $packageProduct) => $packageProduct->embellishments
                ->map(fn (PackageProductEmbellishment $embellishment) => [$packageProduct, $embellishment]));
    }

    /** @param array{0: PackageProduct, 1: PackageProductEmbellishment} $row */
    public function map($row): array
    {
        [$packageProduct, $embellishment] = $row;

        return [
            $embellishment->id,
            $packageProduct->product?->barcode,
            $packageProduct->product?->name,
            $embellishment->embellishment?->name,
            $embellishment->position?->name,
            $embellishment->override_price,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
