<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPick;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Warehouse;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class OrderPicking extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Order Picking';
    protected static ?string $title = 'Order Picking';
    protected static ?string $navigationGroup = 'Orders';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.order-picking';

    /** @var array<int, array{id: int, name: string}> */
    public array $warehouses = [];

    public ?int $selectedWarehouseId = null;

    public ?int $selectedOrderId = null;

    public ?int $selectedItemId = null;

    /** Picking is tracked per (item, size) — sizes are separate barcoded/stocked catalog products, not just descriptive text on the item. */
    public ?string $selectedSize = null;

    public string $barcodeInput = '';

    public ?bool $barcodeMatched = null;

    public ?string $scannedProductName = null;

    /** True once the active size's barcode has been confirmed this session (or there's no catalog variant to scan at all) — required before Save is allowed. */
    public bool $itemConfirmed = false;

    /** True when the catalog has no matching size-variant product for the active item — barcode/stock just aren't available for this size. */
    public bool $noVariantFound = false;

    public int $stagedQty = 0;

    public ?string $stagedNote = null;

    public ?int $warehouseStockQty = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyPermission(['manage_picking', 'pick_orders']) ?? false;
    }

    public function mount(): void
    {
        $this->warehouses = Warehouse::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'name' => $warehouse->name])
            ->values()
            ->all();

        $this->selectedWarehouseId = auth()->user()?->warehouse_id ?: ($this->warehouses[0]['id'] ?? null);
    }

    /** Orders assigned to the current user that still need picking (fully or partially). */
    public function getMyOrdersProperty()
    {
        return Order::where('picking_assigned_to', auth()->id())
            ->where('picking_status', '!=', 'picked')
            ->with('club')
            ->withCount('orderItems')
            ->latest('picking_sent_at')
            ->get();
    }

    public function getSelectedOrderProperty(): ?Order
    {
        if (! $this->selectedOrderId) {
            return null;
        }

        return Order::where('picking_assigned_to', auth()->id())
            ->with(['orderItems.product', 'orderItems.cells', 'orderItems.picks'])
            ->find($this->selectedOrderId);
    }

    /**
     * One row per (item, size) for the item list — e.g. "Golem Tshirt XXL"
     * with qty 2 on its own line, separate from "Golem Tshirt L" — never a
     * single item-level total, since picking (and its barcode) is per size.
     *
     * @return array<int, array{item_id: int, size: string, name: string, sku: ?string, ordered: int, picked: int, balance: int, state: string}>
     */
    public function getPickRowsProperty(): array
    {
        $order = $this->selectedOrder;

        if (! $order) {
            return [];
        }

        $rows = [];

        foreach ($order->orderItems as $item) {
            foreach ($item->sizeBreakdown() as $size => $ordered) {
                $rows[] = [
                    'item_id' => $item->id,
                    'size'    => $size,
                    'name'    => $item->product?->name ?? 'Item',
                    'sku'     => $item->product?->default_sku,
                    'ordered' => $ordered,
                    'picked'  => $item->pickedQtyForSize($size),
                    'balance' => $item->balanceForSize($size),
                    'state'   => $item->pickStateForSize($size),
                ];
            }
        }

        return $rows;
    }

    public function getActiveItemProperty(): ?OrderItem
    {
        if (! $this->selectedItemId) {
            return null;
        }

        return $this->selectedOrder?->orderItems->firstWhere('id', $this->selectedItemId);
    }

    /** The real barcoded/stocked catalog product for the active (item, size) — resolved via parent_sku, not the item's own parent-style product. */
    public function getActiveVariantProperty(): ?Product
    {
        return ($this->activeItem && $this->selectedSize)
            ? $this->activeItem->resolveSizeVariant($this->selectedSize)
            : null;
    }

    public function selectOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $this->selectedItemId = null;
        $this->selectedSize = null;
    }

    public function backToOrders(): void
    {
        $this->selectedOrderId = null;
        $this->selectedItemId = null;
        $this->selectedSize = null;
    }

    public function selectItem(int $itemId, string $size): void
    {
        $this->selectedItemId = $itemId;
        $this->selectedSize = $size;
        $this->barcodeInput = '';
        $this->barcodeMatched = null;
        $this->scannedProductName = null;

        $item = $this->activeItem;

        if (! $item) {
            return;
        }

        $this->stagedQty = $item->pickedQtyForSize($size);
        $this->stagedNote = $item->pickFor($size)?->picking_note;

        $variant = $this->activeVariant;
        $this->noVariantFound = ! $variant;
        // No catalog barcode to scan for this size at all — don't block
        // picking on it, just skip straight to an unlocked qty/note entry.
        $this->itemConfirmed = ! $variant;

        $this->refreshWarehouseStock();
    }

    public function backToItems(): void
    {
        $this->selectedItemId = null;
        $this->selectedSize = null;
    }

    /** Livewire lifecycle hook — fires automatically when the warehouse <select> (wire:model.live) changes. */
    public function updatedSelectedWarehouseId(): void
    {
        $this->refreshWarehouseStock();
    }

    private function refreshWarehouseStock(): void
    {
        $variant = $this->activeVariant;

        $this->warehouseStockQty = ($variant && $this->selectedWarehouseId)
            ? (int) (ProductWarehouseStock::where('product_id', $variant->id)
                ->where('warehouse_id', $this->selectedWarehouseId)
                ->value('qty') ?? 0)
            : null;
    }

    /**
     * Livewire lifecycle hook — fires automatically (after the
     * `.debounce.200ms` on the field's wire:model.live settles) whenever
     * barcodeInput changes. This is what makes scanning work even on
     * scanners (several Zebra DataWedge "Keystroke output" profiles by
     * default) that inject a barcode's characters but never send a
     * trailing Enter/terminator, so the explicit wire:keydown.enter below
     * never fires. Enter still wins the race when a scanner does send one —
     * verifyBarcode() below is a no-op on an already-emptied barcodeInput,
     * so there's no double-processing either way.
     */
    public function updatedBarcodeInput(): void
    {
        $this->verifyBarcode();
    }

    /**
     * Mirrors the Stock Scanner's "confirm, then commit" idiom: the first
     * scan of this size's barcode just confirms it's the right physical
     * item and shows its current warehouse stock (unlocking manual
     * entry/save) without touching the qty; every scan after that adds 1
     * to the picked qty, so repeatedly scanning the same barcode is how
     * you count units as you physically grab them.
     */
    public function verifyBarcode(): void
    {
        $barcode = trim($this->barcodeInput);
        $this->barcodeInput = '';

        $item = $this->activeItem;
        $variant = $this->activeVariant;

        if ($barcode === '' || ! $item || ! $variant) {
            return;
        }

        $product = Product::where('barcode', $barcode)->first();

        $this->scannedProductName = $product?->name;
        $this->barcodeMatched = (bool) ($product && $product->id === $variant->id);

        if (! $this->barcodeMatched) {
            return;
        }

        if ($this->itemConfirmed) {
            $this->stagedQty = max(0, min($item->orderedQtyForSize($this->selectedSize), $this->stagedQty + 1));
        } else {
            $this->itemConfirmed = true;
        }
    }

    public function adjustStagedQty(int $delta): void
    {
        $item = $this->activeItem;

        if (! $item || ! $this->selectedSize || ! $this->itemConfirmed) {
            return;
        }

        $this->stagedQty = max(0, min($item->orderedQtyForSize($this->selectedSize), $this->stagedQty + $delta));
    }

    /** Livewire lifecycle hook — clamps a manually-typed qty into range as soon as it's entered. */
    public function updatedStagedQty(): void
    {
        $item = $this->activeItem;

        if (! $item || ! $this->selectedSize) {
            return;
        }

        $this->stagedQty = max(0, min($item->orderedQtyForSize($this->selectedSize), (int) $this->stagedQty));
    }

    public function savePick(): void
    {
        $item = $this->activeItem;
        $size = $this->selectedSize;

        if (! $item || ! $size || ! $this->itemConfirmed) {
            return;
        }

        $newQty = max(0, min($item->orderedQtyForSize($size), $this->stagedQty));
        $variant = $this->activeVariant;
        $warehouseId = $this->selectedWarehouseId;
        $note = $this->stagedNote;

        DB::transaction(function () use ($item, $size, $newQty, $variant, $warehouseId, $note) {
            $pick = OrderItemPick::firstOrNew(['order_item_id' => $item->id, 'size' => $size]);
            $previousQty = $pick->picked_qty ?? 0;
            $delta = $newQty - $previousQty;

            $stockNote = 'no catalog stock record for this size';

            if ($variant && $warehouseId && $delta !== 0) {
                $currentQty = ProductWarehouseStock::where('product_id', $variant->id)
                    ->where('warehouse_id', $warehouseId)
                    ->value('qty') ?? 0;

                ProductWarehouseStock::applyQty($variant->id, $warehouseId, $currentQty - $delta);
                $variant->recalculateStock();

                $warehouseName = Warehouse::find($warehouseId)?->name ?? "warehouse #{$warehouseId}";
                $stockNote = "stock at {$warehouseName} ".($delta > 0 ? 'decreased' : 'increased')." by ".abs($delta);
            }

            $pick->fill([
                'picked_qty'   => $newQty,
                'picking_note' => $note,
                'picked_by'    => auth()->id(),
                'picked_at'    => now(),
            ])->save();

            $order = $item->order;
            $order->recalculatePickingStatus();

            // Order doesn't use the LogsActivity trait — the stock write
            // above logs its own entry against the ProductWarehouseStock
            // subject, not the order, so without this explicit entry
            // picking activity would never show up on the order's own
            // "Order Log" widget.
            activity('order')
                ->causedBy(auth()->user())
                ->performedOn($order)
                ->withChanges([
                    'attributes' => ['picked_qty' => $newQty],
                    'old'        => ['picked_qty' => $previousQty],
                ])
                ->log("Picked {$item->product?->name} ({$size}): {$previousQty} → {$newQty} of {$item->orderedQtyForSize($size)} — {$stockNote}");
        });

        Notification::make()->title('Item updated')->success()->send();

        $this->selectedItemId = null;
        $this->selectedSize = null;
        $this->resetComputedCaches();
    }

    public function finishPicking(): void
    {
        $order = $this->selectedOrder;

        if (! $order) {
            return;
        }

        $order->recalculatePickingStatus();
        $order->update([
            'picking_assigned_to'  => null,
            'picking_completed_at' => now(),
        ]);

        $statusLabel = OrderResource::PICKING_STATUSES[$order->fresh()->picking_status] ?? $order->picking_status;

        activity('order')
            ->causedBy(auth()->user())
            ->performedOn($order)
            ->withChanges([
                'attributes' => ['picking_status' => $statusLabel],
                'old'        => ['picking_status' => null],
            ])
            ->log("Finished picking — {$statusLabel}");

        Notification::make()->title('Order updated')->success()->send();

        $this->backToOrders();
        $this->resetComputedCaches();
    }

    /**
     * Livewire's legacy get{X}Property() convention memoizes each computed
     * property the first time it's accessed in a request (see
     * SupportLegacyComputedPropertySyntax) — not re-derived on every
     * access like a plain accessor. savePick()/finishPicking() both read
     * one of these (via activeItem/selectedOrder) before writing the DB
     * change, so without busting the cache afterward, the very next render
     * in that same request (going back to the item/order list) would keep
     * showing the pre-save numbers until a separate request re-hydrates
     * the component. unset() is Livewire's own documented way to force a
     * computed property to recompute on its next access.
     */
    private function resetComputedCaches(): void
    {
        unset($this->selectedOrder, $this->activeItem, $this->activeVariant, $this->pickRows, $this->myOrders);
    }
}
