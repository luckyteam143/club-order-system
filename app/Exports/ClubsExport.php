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

class ClubsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'Clubs';
    }

    public function headings(): array
    {
        return [
            'ID', 'Name', 'Club Code', 'Email', 'Phone', 'Address', 'Contact Person', 'Status',
            'Summer Forecast Open', 'Summer Forecast Close',
            'Winter Forecast Open', 'Winter Forecast Close', 'Notes',
        ];
    }

    public function collection(): Collection
    {
        return Club::query()->orderBy('name')->get();
    }

    public function map($club): array
    {
        return [
            $club->id,
            $club->name,
            $club->code,
            $club->email,
            $club->phone,
            $club->address,
            $club->contact_person,
            $club->status,
            optional($club->summer_forecast_open_at)->format('Y-m-d'),
            optional($club->summer_forecast_close_at)->format('Y-m-d'),
            optional($club->winter_forecast_open_at)->format('Y-m-d'),
            optional($club->winter_forecast_close_at)->format('Y-m-d'),
            $club->notes,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
