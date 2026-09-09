<?php

namespace App\Exports;

use App\Models\LogoStock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LogoStockExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'Logos Stock';
    }

    public function headings(): array
    {
        return [
            'Logo Stock ID', 'Club Name', 'Club Code', 'Barcode', 'Logo Type', 'Stock Type',
            'Logo Name', 'Width', 'Height', 'Location', 'Warehouse', 'Position', 'Qty',
            'Vector File Link', 'Notes',
        ];
    }

    public function collection(): Collection
    {
        return LogoStock::with(['club', 'logoType', 'warehouse'])
            ->orderBy('position')
            ->get();
    }

    public function map($logoStock): array
    {
        return [
            $logoStock->id,
            $logoStock->club?->name,
            $logoStock->club?->code,
            $logoStock->barcode,
            $logoStock->logoType?->name,
            match ($logoStock->logo_stock_type) {
                'numbers' => 'Numbers',
                'sponsor' => 'Sponsor',
                default   => 'Logo',
            },
            $logoStock->logo_name,
            $logoStock->width,
            $logoStock->height,
            $logoStock->location,
            $logoStock->warehouse?->name,
            $logoStock->position,
            $logoStock->qty,
            $logoStock->vector_file_link,
            $logoStock->notes,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
