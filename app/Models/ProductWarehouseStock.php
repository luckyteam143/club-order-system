<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductWarehouseStock extends Model
{
    use LogsActivity;

    protected $table = 'product_warehouse_stock';

    protected $fillable = ['product_id', 'warehouse_id', 'qty', 'qty_on_hold'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['qty', 'qty_on_hold'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock')
            ->setDescriptionForEvent(function (string $eventName) {
                $productName = $this->product?->name ?? "Product #{$this->product_id}";
                $warehouseName = $this->warehouse?->name ?? "Warehouse #{$this->warehouse_id}";

                if ($eventName === 'updated') {
                    $old = $this->oldAttributes ?? [];
                    $changes = [];

                    if (array_key_exists('qty', $old) && (int) $old['qty'] !== (int) $this->qty) {
                        $changes[] = "stock {$old['qty']} → {$this->qty}";
                    }

                    if (array_key_exists('qty_on_hold', $old) && (int) $old['qty_on_hold'] !== (int) $this->qty_on_hold) {
                        $changes[] = "on hold {$old['qty_on_hold']} → {$this->qty_on_hold}";
                    }

                    $summary = $changes ? implode(', ', $changes) : 'no change';

                    return "Stock for \"{$productName}\" at {$warehouseName} changed ({$summary})";
                }

                return "Stock line for \"{$productName}\" at {$warehouseName} has been {$eventName}";
            });
    }

    /**
     * The single write path for the scanner, bulk grid save, inline listing
     * save, and Excel import, so the clamp-at-0 and find-or-create logic
     * isn't duplicated four times over. Each real qty change logs its own
     * row as normal via the LogsActivity trait above (getActivitylogOptions()) —
     * one entry per scan/edit, not folded into a running daily total.
     */
    public static function applyQty(int $productId, int $warehouseId, int $qty): self
    {
        $qty = max(0, $qty);

        $stock = static::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['qty' => 0],
        );

        if ((int) $stock->qty !== $qty) {
            $stock->update(['qty' => $qty]);
        }

        return $stock;
    }

    /**
     * Absolute set of the on-hold reserve for a product+warehouse, clamped
     * at 0 — the on-hold counterpart of applyQty(). Available `qty` is left
     * untouched; this is what the "Update On Hold Qty" scanner mode uses.
     */
    public static function applyOnHoldQty(int $productId, int $warehouseId, int $qty): self
    {
        $qty = max(0, $qty);

        $stock = static::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['qty' => 0],
        );

        if ((int) $stock->qty_on_hold !== $qty) {
            $stock->update(['qty_on_hold' => $qty]);
        }

        return $stock;
    }

    /**
     * Reserve stock: move up to $amount units out of available `qty` and
     * into `qty_on_hold` for this product+warehouse, in one logged write.
     * Never moves more than is physically on hand. Backs the scanner's
     * "Put On Hold" mode, where removing stock parks it here instead of
     * discarding it.
     */
    public static function moveToHold(int $productId, int $warehouseId, int $amount): self
    {
        $amount = max(0, $amount);

        $stock = static::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['qty' => 0],
        );

        $moved = min($amount, (int) $stock->qty);

        if ($moved > 0) {
            $stock->update([
                'qty'         => (int) $stock->qty - $moved,
                'qty_on_hold' => (int) $stock->qty_on_hold + $moved,
            ]);
        }

        return $stock;
    }
}
