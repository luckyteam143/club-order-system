<?php

namespace App\Imports;

use App\Models\Club;
use App\Models\ClubTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Matches ClubTeamExport's layout for a round-trip export -> edit -> import.
 * Columns: Team ID (optional — updates that exact row if given), Club Name
 * and/or Club Code (at least one, resolves an existing club), Team Name,
 * PO Reference, Coach/Manager Name/Email/Contact, Address, Status, Sort Order.
 *
 * Rows without a Team ID are matched against an existing club+team-name
 * combination rather than always creating a new row, so re-importing the
 * same sheet doesn't pile up duplicates — same idea as LogoStockImport.
 */
class ClubTeamImport implements SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
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
                $teamName = trim((string) ($row['team_name'] ?? ''));

                if (! $club || $teamName === '') {
                    continue;
                }

                $data = [
                    'club_id'                => $club->id,
                    'team_name'               => $teamName,
                    'po_reference'            => trim((string) ($row['po_reference'] ?? '')) ?: null,
                    'coach_manager_name'      => trim((string) ($row['coach_manager_name'] ?? '')) ?: null,
                    'coach_manager_email'     => trim((string) ($row['coach_manager_email'] ?? '')) ?: null,
                    'coach_manager_contact'   => trim((string) ($row['coach_manager_contact'] ?? '')) ?: null,
                    'address'                 => trim((string) ($row['address'] ?? '')) ?: null,
                    'status'                  => $this->resolveStatus($row),
                    'sort_order'              => (int) ($row['sort_order'] ?? 0),
                ];

                if (! empty($row['team_id'])) {
                    ClubTeam::where('id', (int) $row['team_id'])->update($data);

                    continue;
                }

                ClubTeam::updateOrCreate(
                    ['club_id' => $club->id, 'team_name' => $teamName],
                    $data,
                );
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

    protected function resolveStatus(Collection $row): string
    {
        $value = strtolower(trim((string) ($row['status'] ?? '')));

        return $value === 'inactive' ? 'inactive' : 'active';
    }

    public function rules(): array
    {
        return [
            'team_name' => 'required|string',
            'sort_order' => 'nullable|numeric',
        ];
    }
}
