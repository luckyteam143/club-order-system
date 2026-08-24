<?php

namespace App\Imports;

use App\Models\Club;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Matches ClubsExport's layout for a round-trip export -> edit -> import.
 * Columns: ID (optional, updates that exact row), Name, Club Code, Email,
 * Phone, Address, Contact Person, Status, Summer/Winter Forecast Open/Close
 * (Y-m-d), Notes.
 *
 * Rows without an ID are matched against an existing club by Code (if
 * given) then Email, so re-importing the same sheet updates in place
 * instead of creating duplicates — same idea as ProductsImport.
 *
 * Deliberately NOT WithChunkReading: club lists are small (dozens, not
 * thousands), and sheets exported/edited in Excel or LibreOffice often carry
 * a bogus "used range" that extends to the sheet's max row (e.g. from
 * formatting whole columns). Chunked reading re-parses the file per chunk
 * against that inflated row count and can time out long before reaching the
 * real data — a single unchunked read doesn't have that problem.
 */
class ClubsImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));

                if ($name === '' || $email === '') {
                    continue;
                }

                $data = [
                    'name'                     => $name,
                    'code'                     => $this->nullableString($row['club_code'] ?? $row['code'] ?? null),
                    'email'                    => $email,
                    'phone'                    => $this->nullableString($row['phone'] ?? null),
                    'address'                  => $this->nullableString($row['address'] ?? null),
                    'contact_person'           => $this->nullableString($row['contact_person'] ?? null),
                    'status'                   => $this->nullableString($row['status'] ?? null) ?? 'active',
                    'summer_forecast_open_at'  => $this->nullableString($row['summer_forecast_open'] ?? null),
                    'summer_forecast_close_at' => $this->nullableString($row['summer_forecast_close'] ?? null),
                    'winter_forecast_open_at'  => $this->nullableString($row['winter_forecast_open'] ?? null),
                    'winter_forecast_close_at' => $this->nullableString($row['winter_forecast_close'] ?? null),
                    'notes'                    => $this->nullableString($row['notes'] ?? null),
                ];

                $club = null;

                if (! empty($row['id'])) {
                    $club = Club::find((int) $row['id']);
                }

                if (! $club && $data['code']) {
                    $club = Club::where('code', $data['code'])->first();
                }

                if (! $club) {
                    $club = Club::where('email', $email)->first();
                }

                if ($club) {
                    $club->update($data);
                } else {
                    Club::create($data);
                }
            }
        });
    }

    protected function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Trims stray whitespace (common in pasted/exported sheets — e.g. "foo@bar.com "
     * failing the email rule) and normalizes "Active"/"INACTIVE" style casing
     * to match the lowercase values the status column actually stores.
     */
    public function prepareForValidation(array $data, int $index): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        if (isset($data['status']) && $data['status'] !== '') {
            $data['status'] = strtolower($data['status']);
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:255',
            'email'  => 'required|email',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
