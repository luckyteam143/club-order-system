<?php

namespace App\Imports;

use App\Models\Brochure;
use App\Models\Club;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Matches BrochuresExport's layout for a round-trip export -> edit ->
 * import. Columns: ID (optional, updates that exact row), Club Name / Club
 * Code (at least one, resolves an existing club), Brochure Link, Created
 * Date, Approved Date (Y-m-d, blank = not yet approved), Notes.
 */
class BrochuresImport implements SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public function chunkSize(): int
    {
        return 200;
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $club = $this->resolveClub($row);
                $link = trim((string) ($row['brochure_link'] ?? ''));
                $createdDate = $this->nullableString($row['created_date'] ?? null);

                if (! $club || $link === '' || ! $createdDate) {
                    continue;
                }

                $data = [
                    'club_id'       => $club->id,
                    'link'          => $link,
                    'created_date'  => $createdDate,
                    'approved_date' => $this->nullableString($row['approved_date'] ?? null),
                    'notes'         => $this->nullableString($row['notes'] ?? null),
                ];

                if (! empty($row['id'])) {
                    Brochure::where('id', (int) $row['id'])->update($data);

                    continue;
                }

                Brochure::create($data);
            }
        });
    }

    protected function resolveClub(Collection $row): ?Club
    {
        $code = trim((string) ($row['club_code'] ?? ''));

        if ($code !== '') {
            $club = Club::where('code', $code)->first();

            if ($club) {
                return $club;
            }
        }

        $name = trim((string) ($row['club_name'] ?? ''));

        return $name === '' ? null : Club::where('name', $name)->first();
    }

    protected function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function rules(): array
    {
        return [
            'brochure_link' => 'required|string',
            'created_date'  => 'required',
        ];
    }
}
