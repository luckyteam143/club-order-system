<?php

namespace App\Exports;

use App\Models\Media;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Round-trips with MediaImport: export, edit the Name / File Name columns in
 * Excel, import back. "File Name" is the base name only (e.g. logo.png) —
 * the import re-attaches the "media/" folder itself.
 */
class MediaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'Media';
    }

    public function headings(): array
    {
        return ['Media ID', 'Name', 'File Name'];
    }

    public function collection(): Collection
    {
        return Media::orderBy('name')->get();
    }

    public function map($media): array
    {
        return [
            $media->id,
            $media->name,
            $media->file ? basename($media->file) : null,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
