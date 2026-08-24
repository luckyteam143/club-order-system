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
    public function scanBarcode(string $barcode, int $warehouseId, ?int $currentProductId, int $delta): ?array
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

        return DB::transaction(function () use ($product, $delta, $warehouseId) {
            $currentQty = ProductWarehouseStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->value('qty') ?? 0;

            ProductWarehouseStock::applyQty($product->id, $warehouseId, $currentQty + $delta);
            $product->recalculateStock();

            return [...$this->productPayload($product, $warehouseId), 'adjusted' => true];
        });
    }

    /**
     * Relative adjustment — one scan (or tap of +/-) in Add/Remove mode.
     * Clamped at 0, never goes negative.
     */
    public function adjustStock(int $productId, int $delta, int $warehouseId): ?array
    {
        if (! $this->validWarehouseId($warehouseId)) {
            return null;
        }

        return DB::transaction(function () use ($productId, $delta, $warehouseId) {
            $product = Product::find($productId);

            if (! $product) {
                return null;
            }

            $currentQty = ProductWarehouseStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->value('qty') ?? 0;

            ProductWarehouseStock::applyQty($productId, $warehouseId, $currentQty + $delta);
            $product->recalculateStock();

            return $this->productPayload($product, $warehouseId);
        });
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
        $qty = ProductWarehouseStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->value('qty') ?? 0;

        return [
            'id'      => $product->id,
            'name'    => $product->name,
            'barcode' => $product->barcode,
            'size'    => $product->size,
            'qty'     => (int) $qty,
        ];
    }
}
