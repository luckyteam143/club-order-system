<?php

namespace App\Exports;

use App\Models\Club;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One club's assigned items with their two pivot prices. Round-trips with
 * ClubItemsImport: export, edit prices in Excel, re-import. An empty club
 * still exports the header row, so this doubles as the import template.
 */
class ClubItemsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private Club $club)
    {
    }

    public function title(): string
    {
        return 'Club Items';
    }

    public function headings(): array
    {
        return ['ID', 'Barcode', 'Name', 'Club Price', 'Online Store Price', 'Has Club Crest', 'Crest Number'];
    }

    public function collection(): Collection
    {
        return $this->club->products()->orderBy('name')->get();
    }

    public function map($product): array
    {
        return [
            $product->pivot->id,
            $product->barcode,
            $product->name,
            $product->pivot->club_price,
            $product->pivot->online_store_price,
            $product->pivot->has_club_crest ? 'Yes' : 'No',
            $product->pivot->crest_number ?? 1,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
