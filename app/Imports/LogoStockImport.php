<?php

namespace App\Imports;

use App\Models\Club;
use App\Models\LogoStock;
use App\Models\LogoType;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Matches LogoStockExport's layout for a round-trip export -> edit -> import.
 * Columns: Logo Stock ID (optional — updates that exact row if given),
 * Club Name / Club Code (at least one, resolves an existing club), Barcode,
 * Logo Type (name, must already exist), Stock Type ("Logo", "Numbers" or
 * "Sponsor", defaults to "Logo"), Logo Name, Width, Height, Location,
 * Warehouse (name or code), Position, Qty, Vector File Link, Notes.
 *
 * Rows without a Logo Stock ID are matched against an existing
 * club+logo_type+warehouse combination (if one exists) rather than always
 * creating a new row, so re-importing the same sheet doesn't pile up
 * duplicates — same idea as ProductWarehouseStock::applyQty's
 * firstOrCreate-by-composite-key, just spread across every field here
 * instead of qty alone.
 */
class LogoStockImport implements SkipsOnFailure, ToCollection, WithChunkReading, WithHeadingRow, WithValidation
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
                $logoType = $this->resolveLogoType($row);
                $warehouse = $this->resolveWarehouse($row);
                $barcode = trim((string) ($row['barcode'] ?? ''));
                $logoName = trim((string) ($row['logo_name'] ?? ''));

                if (! $club || ! $logoType || ! $warehouse || $barcode === '' || $logoName === '') {
                    continue;
                }

                $data = [
                    'club_id'          => $club->id,
                    'logo_type_id'     => $logoType->id,
                    'logo_stock_type'  => $this->resolveStockType($row),
                    'warehouse_id'     => $warehouse->id,
                    'barcode'          => $barcode,
                    'logo_name'        => $logoName,
                    'width'            => trim((string) ($row['width'] ?? '')) ?: null,
                    'height'           => trim((string) ($row['height'] ?? '')) ?: null,
                    'location'         => trim((string) ($row['location'] ?? '')) ?: null,
                    'position'         => (int) ($row['position'] ?? 0),
                    'qty'              => max(0, (int) ($row['qty'] ?? 0)),
                    'vector_file_link' => trim((string) ($row['vector_file_link'] ?? '')) ?: null,
                    'notes'            => trim((string) ($row['notes'] ?? '')) ?: null,
                ];

                if (! empty($row['logo_stock_id'])) {
                    LogoStock::where('id', (int) $row['logo_stock_id'])->update($data);

                    continue;
                }

                LogoStock::updateOrCreate(
                    ['club_id' => $club->id, 'logo_type_id' => $logoType->id, 'warehouse_id' => $warehouse->id],
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

    protected function resolveStockType(Collection $row): string
    {
        $value = strtolower(trim((string) ($row['stock_type'] ?? $row['logo_stock_type'] ?? '')));

        return in_array($value, ['numbers', 'sponsor'], true) ? $value : 'logo';
    }

    protected function resolveLogoType(Collection $row): ?LogoType
    {
        $name = trim((string) ($row['logo_type'] ?? ''));

        return $name === '' ? null : LogoType::where('name', $name)->first();
    }

    protected function resolveWarehouse(Collection $row): ?Warehouse
    {
        $name = trim((string) ($row['warehouse'] ?? ''));

        if ($name === '') {
            return null;
        }

        return Warehouse::where('name', $name)
            ->orWhere('code', $name)
            ->first();
    }

    public function rules(): array
    {
        return [
            'barcode'   => 'required|string',
            'logo_name' => 'required|string',
            'warehouse' => 'required|string',
            'logo_type' => 'required|string',
            'qty'       => 'nullable|numeric',
            'position'  => 'nullable|numeric',
        ];
    }
}
