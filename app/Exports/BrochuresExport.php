<?php

namespace App\Exports;

use App\Models\Brochure;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BrochuresExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'Brochures';
    }

    public function headings(): array
    {
        return ['ID', 'Club Name', 'Club Code', 'Brochure Link', 'Created Date', 'Approved Date', 'Notes'];
    }

    public function collection(): Collection
    {
        return Brochure::with('club:id,name,code')->orderByDesc('created_date')->get();
    }

    public function map($brochure): array
    {
        return [
            $brochure->id,
            $brochure->club?->name,
            $brochure->club?->code,
            $brochure->link,
            optional($brochure->created_date)->format('Y-m-d'),
            optional($brochure->approved_date)->format('Y-m-d'),
            $brochure->notes,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
