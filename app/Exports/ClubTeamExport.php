<?php

namespace App\Exports;

use App\Models\ClubTeam;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClubTeamExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'Club Teams';
    }

    public function headings(): array
    {
        return [
            'Team ID', 'Club Name', 'Club Code', 'Team Name', 'PO Reference',
            'Coach Manager Name', 'Coach Manager Email', 'Coach Manager Contact',
            'Address', 'Status', 'Sort Order',
        ];
    }

    public function collection(): Collection
    {
        return ClubTeam::with('club')
            ->orderBy('club_id')
            ->orderBy('sort_order')
            ->get();
    }

    public function map($team): array
    {
        return [
            $team->id,
            $team->club?->name,
            $team->club?->code,
            $team->team_name,
            $team->po_reference,
            $team->coach_manager_name,
            $team->coach_manager_email,
            $team->coach_manager_contact,
            $team->address,
            $team->status,
            $team->sort_order,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
