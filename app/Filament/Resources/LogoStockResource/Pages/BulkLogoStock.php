<?php

namespace App\Filament\Resources\LogoStockResource\Pages;

use App\Filament\Resources\LogoStockResource;
use App\Models\Club;
use App\Models\LogoStock;
use App\Models\LogoType;
use App\Models\Warehouse;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class BulkLogoStock extends Page
{
    protected static string $resource = LogoStockResource::class;

    protected static string $view = 'filament.resources.logo-stock-resource.pages.bulk-logo-stock';

    protected static ?string $title = 'Bulk Add / Edit Logos Stock';

    /** @var array<int, array{id: int, name: string, code: ?string}> */
    public array $clubs = [];

    /** @var array<int, array{id: int, name: string}> */
    public array $logoTypes = [];

    /** @var array<int, array{id: int, name: string}> */
    public array $warehouses = [];

    // Prefills the grid's search box when arriving via a "?q=" link (e.g.
    // the Logos Stock listing's per-row Edit action).
    public string $initialSearch = '';

    public function mount(): void
    {
        $this->initialSearch = (string) request()->query('q', '');

        // Clubs and lookup tables are small enough to embed up front —
        // unlike the Product catalog's Bulk Stock grid, no remote search is
        // needed for the row dropdowns.
        $this->clubs = Club::orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Club $club) => ['id' => $club->id, 'name' => $club->name, 'code' => $club->code])
            ->values()
            ->all();

        $this->logoTypes = LogoType::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (LogoType $type) => ['id' => $type->id, 'name' => $type->name])
            ->values()
            ->all();

        $this->warehouses = Warehouse::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'name' => $warehouse->name])
            ->values()
            ->all();
    }

    /**
     * Server-side search backing the grid's search box — matches on the
     * club's name/code or the logo-stock row's own barcode, per the "search
     * by club name or barcode" requirement.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchLogoStock(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        return LogoStock::query()
            ->with(['club:id,name,code'])
            ->where(function ($builder) use ($query) {
                $builder->where('barcode', 'like', "%{$query}%")
                    ->orWhereHas('club', function ($clubQuery) use ($query) {
                        $clubQuery->where('name', 'like', "%{$query}%")
                            ->orWhere('code', 'like', "%{$query}%");
                    });
            })
            ->orderBy('position')
            ->limit(200)
            ->get()
            ->map(fn (LogoStock $row) => $this->rowPayload($row))
            ->values()
            ->all();
    }

    private function rowPayload(LogoStock $row): array
    {
        return [
            'id'           => $row->id,
            'club_id'      => $row->club_id,
            'club_label'   => $row->club ? $row->club->name.($row->club->code ? " ({$row->club->code})" : '') : '',
            'barcode'      => $row->barcode,
            'logo_type_id' => $row->logo_type_id,
            'logo_stock_type' => $row->logo_stock_type,
            'location'     => $row->location,
            'logo_name'    => $row->logo_name,
            'size'         => $row->size,
            'warehouse_id' => $row->warehouse_id,
            'position'     => $row->position,
            'qty'          => $row->qty,
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('manage_logo_stock') ?? false;
    }

    /**
     * Backs the grid's club dropdown on a brand-new row: mirrors
     * LogoStockResource's club_id afterStateUpdated (next open position =
     * existing max for that club + 1), since new rows here start at
     * position 0 with no server round-trip otherwise available to compute
     * it client-side.
     */
    public function nextPositionForClub(int $clubId): int
    {
        return (int) (LogoStock::where('club_id', $clubId)->max('position') ?? 0) + 1;
    }

    /**
     * Persists every row from the grid in one go — rows with a numeric id
     * are updated in place, rows without one (added via "+ Add Row") are
     * created. Rows missing a required field are silently skipped rather
     * than failing the whole batch, and counted back to the user.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function saveRows(array $rows): void
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $data = [
                    'club_id'      => $row['club_id'] ?: null,
                    'barcode'      => trim((string) ($row['barcode'] ?? '')),
                    'logo_type_id' => $row['logo_type_id'] ?: null,
                    'logo_stock_type' => in_array($row['logo_stock_type'] ?? null, ['logo', 'numbers'], true) ? $row['logo_stock_type'] : 'logo',
                    'location'     => $row['location'] !== '' ? $row['location'] : null,
                    'logo_name'    => trim((string) ($row['logo_name'] ?? '')),
                    'size'         => trim((string) ($row['size'] ?? '')) ?: null,
                    'warehouse_id' => $row['warehouse_id'] ?: null,
                    'position'     => (int) ($row['position'] ?? 0),
                    'qty'          => max(0, (int) ($row['qty'] ?? 0)),
                ];

                if (! $data['club_id'] || ! $data['logo_type_id'] || ! $data['warehouse_id']
                    || $data['barcode'] === '' || $data['logo_name'] === '') {
                    $skipped++;

                    continue;
                }

                $id = $row['id'] ?? null;

                if (is_numeric($id)) {
                    LogoStock::whereKey($id)->update($data);
                    $updated++;
                } else {
                    LogoStock::create($data);
                    $created++;
                }
            }
        });

        Notification::make()
            ->title("{$created} added, {$updated} updated".($skipped ? ", {$skipped} skipped (missing required fields)" : ''))
            ->success()
            ->send();
    }
}
