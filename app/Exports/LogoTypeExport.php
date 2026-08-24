<?php

namespace App\Exports;

use App\Models\LogoType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LogoTypeExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'Logo Types';
    }

    public function headings(): array
    {
        return ['Logo Type ID', 'Name'];
    }

    public function collection(): Collection
    {
        return LogoType::orderBy('name')->get();
    }

    public function map($logoType): array
    {
        return [
            $logoType->id,
            $logoType->name,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
