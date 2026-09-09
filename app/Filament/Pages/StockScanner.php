<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Warehouse;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class StockScanner extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Scan Stock';
    protected static ?string $title = 'Scan Stock';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.stock-scanner';

    /** @var array<int, array{id: int, name: string}> */
    public array $warehouses = [];

    public ?int $assignedWarehouseId = null;

    public ?int $initialWarehouseId = null;

    /**
     * True when the user has a default warehouse on their account — the
     * page then skips straight to scanning instead of making them confirm
     * one first.
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
        return auth()->user()?->hasAnyPermission(['manage_stock', 'scan_stock']) ?? false;
    }

    /**
     * Every scan/adjust/set call below takes the warehouse id explicitly
     * from the front end rather than reading a Livewire property — the
     * warehouse selector is plain Alpine state (no server round trip on
     * every change), so a stored `$this->warehouseId` would silently go
     * stale the moment the user switched warehouses in the UI, applying
     * every subsequent scan to the wrong one.
     */
    private function validWarehouseId(int $warehouseId): bool
    {
        return Warehouse::where('id', $warehouseId)->where('status', 'active')->exists();
    }

    /**
     * The scanner's single call per scan event — combines the old
     * lookup+adjust into one round trip (halves scan latency) and encodes
     * the "confirm, then commit" rule: scanning an item that isn't already
     * the one on screen just loads and shows its current stock; scanning
     * the SAME item again (the one already loaded) is what actually
     * applies the add/remove.
     */
    public function scanBarcode(string $barcode, int $warehouseId, ?int $currentProductId, int $delta, string $holdMode = 'none'): ?array
    {
        $barcode = trim($barcode);

        if ($barcode === '' || ! $this->validWarehouseId($warehouseId)) {
            return null;
        }

        $product = Product::where('barcode', $barcode)->where('status', 'Active')->first();

        if (! $product) {
            return null;
        }

        if ($currentProductId !== $product->id) {
            return [...$this->productPayload($product, $warehouseId), 'adjusted' => false];
        }

        return [
            ...$this->applyMovement($product, $warehouseId, $delta, $this->normalizeHoldMode($holdMode)),
            'adjusted' => true,
        ];
    }

    /**
     * Relative adjustment — one scan (or tap of +/-) in Add/Remove mode.
     * Clamped at 0, never goes negative.
     */
    public function adjustStock(int $productId, int $delta, int $warehouseId, string $holdMode = 'none'): ?array
    {
        if (! $this->validWarehouseId($warehouseId)) {
            return null;
        }

        $product = Product::find($productId);

        if (! $product) {
            return null;
        }

        return $this->applyMovement($product, $warehouseId, $delta, $this->normalizeHoldMode($holdMode));
    }

    /**
     * The single commit path shared by scanBarcode() and adjustStock(),
     * branching on the scanner's on-hold mode:
     *
     *  - 'none'   : normal — $delta adjusts available stock (qty).
     *  - 'put'    : only a removal does anything — whatever is actually
     *               taken out of available stock is parked in qty_on_hold
     *               instead of discarded. A positive $delta falls through to
     *               normal "add to stock" behaviour.
     *  - 'update' : $delta adjusts qty_on_hold directly; available stock
     *               (qty) is never touched.
     *
     * @return array{id: int, name: string, barcode: ?string, size: ?string, qty: int, qty_on_hold: int}
     */
    private function applyMovement(Product $product, int $warehouseId, int $delta, string $holdMode): array
    {
        return DB::transaction(function () use ($product, $warehouseId, $delta, $holdMode) {
            $current = ProductWarehouseStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->first(['qty', 'qty_on_hold']);

            if ($holdMode === 'update') {
                ProductWarehouseStock::applyOnHoldQty(
                    $product->id,
                    $warehouseId,
                    (int) ($current->qty_on_hold ?? 0) + $delta,
                );
            } elseif ($holdMode === 'put' && $delta < 0) {
                ProductWarehouseStock::moveToHold($product->id, $warehouseId, -$delta);
                $product->recalculateStock();
            } else {
                ProductWarehouseStock::applyQty(
                    $product->id,
                    $warehouseId,
                    (int) ($current->qty ?? 0) + $delta,
                );
                $product->recalculateStock();
            }

            return $this->productPayload($product, $warehouseId);
        });
    }

    private function normalizeHoldMode(string $holdMode): string
    {
        return in_array($holdMode, ['put', 'update'], true) ? $holdMode : 'none';
    }

    /**
     * Re-fetches a product's qty for the warehouse just switched to, so a
     * currently-loaded product's displayed qty stays correct after
     * changing warehouse mid-session instead of showing a stale number.
     */
    public function refreshForWarehouse(int $productId, int $warehouseId): ?array
    {
        if (! $this->validWarehouseId($warehouseId)) {
            return null;
        }

        $product = Product::find($productId);

        return $product ? $this->productPayload($product, $warehouseId) : null;
    }

    private function productPayload(Product $product, int $warehouseId): array
    {
        $stock = ProductWarehouseStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->first(['qty', 'qty_on_hold']);

        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'barcode'     => $product->barcode,
            'size'        => $product->size,
            'qty'         => (int) ($stock->qty ?? 0),
            'qty_on_hold' => (int) ($stock->qty_on_hold ?? 0),
        ];
    }
}
