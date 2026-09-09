<?php

namespace App\Filament\Pages;

use App\Models\Club;
use App\Models\LogoStock;
use App\Models\Warehouse;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class LogoStockScanner extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Scan Logos Stock';
    protected static ?string $title = 'Scan Logos Stock';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.logo-stock-scanner';

    /** @var array<int, array{id: int, name: string}> */
    public array $warehouses = [];

    public ?int $assignedWarehouseId = null;

    public ?int $initialWarehouseId = null;

    /**
     * True when the user has a default warehouse on their account — the
     * page then skips straight to scanning instead of making them confirm
     * one first. Shares the same per-user warehouse_id as the Product
     * Stock Scanner.
     */
    public bool $warehouseConfirmed = false;

    public function mount(): void
    {
        $this->warehouses = Warehouse::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'name' => $warehouse->name])
            ->values()
            ->all();

        $this->assignedWarehouseId = auth()->user()?->warehouse_id;
        $this->warehouseConfirmed = (bool) $this->assignedWarehouseId;
        $this->initialWarehouseId = $this->assignedWarehouseId ?: ($this->warehouses[0]['id'] ?? null);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyPermission(['manage_logo_stock', 'scan_logo_stock']) ?? false;
    }

    /**
     * Every call below takes the warehouse id explicitly from the front end
     * rather than reading a Livewire property — same reasoning as the
     * Product Stock Scanner: the warehouse selector is plain Alpine state,
     * so a stored `$this->warehouseId` would go stale the moment the user
     * switched warehouses in the UI.
     */
    private function validWarehouseId(int $warehouseId): bool
    {
        return Warehouse::where('id', $warehouseId)->where('status', 'active')->exists();
    }

    /**
     * A club's barcode is duplicated across every one of its logo-stock
     * rows — scanning it just needs to find any one row with that barcode
     * (scoped to the current warehouse) to resolve the club, then every
     * logo type that club has stocked in this warehouse is loaded at once.
     *
     * Not every club has a printed barcode handy, so this also accepts the
     * club's own short Code (Club.code) typed/scanned in directly — tried
     * only as a fallback, after the logo-stock barcode lookup misses.
     */
    public function scanBarcode(string $barcode, int $warehouseId): ?array
    {
        $barcode = trim($barcode);

        if ($barcode === '' || ! $this->validWarehouseId($warehouseId)) {
            return null;
        }

        $clubId = LogoStock::where('barcode', $barcode)->where('warehouse_id', $warehouseId)->value('club_id');

        if (! $clubId) {
            $clubId = Club::where('code', $barcode)->value('id');
        }

        if (! $clubId || ! LogoStock::where('club_id', $clubId)->where('warehouse_id', $warehouseId)->exists()) {
            return null;
        }

        return $this->clubPayload($clubId, $warehouseId);
    }

    /**
     * Commits every changed row in one batch, then returns the refreshed
     * list — called only from the scanner's single "Update" button, not on
     * every +/- tap (those only adjust a local, unsaved value on screen).
     *
     * @param  array<int, int>  $updates  logoStockId => new qty
     */
    public function updateLogoStock(int $clubId, int $warehouseId, array $updates): ?array
    {
        if (! $this->validWarehouseId($warehouseId)) {
            return null;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $qty) {
                LogoStock::applyQty((int) $id, (int) $qty);
            }
        });

        return $this->clubPayload($clubId, $warehouseId);
    }

    /**
     * Re-fetches a loaded club's rows for the warehouse just switched to,
     * so the on-screen quantities stay correct after changing warehouse
     * mid-session instead of showing stale numbers.
     */
    public function refreshForWarehouse(int $clubId, int $warehouseId): ?array
    {
        if (! $this->validWarehouseId($warehouseId)) {
            return null;
        }

        return $this->clubPayload($clubId, $warehouseId);
    }

    private function clubPayload(int $clubId, int $warehouseId): array
    {
        $club = Club::find($clubId);

        $rows = LogoStock::where('club_id', $clubId)
            ->where('warehouse_id', $warehouseId)
            ->with('logoType:id,name')
            ->orderBy('position')
            ->get()
            ->map(fn (LogoStock $row) => [
                'id'        => $row->id,
                'logo_type' => $row->logoType?->name,
                'stock_type' => $row->logo_stock_type,
                'logo_name' => $row->logo_name,
                'width'     => $row->width,
                'height'    => $row->height,
                'location'  => $row->location,
                'qty'       => (int) $row->qty,
            ])
            ->values()
            ->all();

        return [
            'club' => ['id' => $clubId, 'name' => $club?->name, 'code' => $club?->code],
            'rows' => $rows,
        ];
    }
}
