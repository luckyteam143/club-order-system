<?php

namespace App\Imports;

use App\Models\LogoType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Matches LogoTypeExport's layout for a round-trip export -> edit -> import.
 * Columns: Logo Type ID (optional — updates that row's name if given),
 * Name (required — otherwise matched/created by name).
 */
class LogoTypeImport implements SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public function chunkSize(): int
    {
        return 200;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if (! empty($row['logo_type_id'])) {
                $logoType = LogoType::find((int) $row['logo_type_id']);

                if ($logoType) {
                    $logoType->update(['name' => $name]);

                    continue;
                }
            }

            LogoType::firstOrCreate(['name' => $name]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }
}
