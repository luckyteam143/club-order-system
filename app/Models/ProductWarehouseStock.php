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

    protected $fillable = ['product_id', 'warehouse_id', 'qty'];

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
            ->logOnly(['qty'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock')
            ->setDescriptionForEvent(function (string $eventName) {
                $productName = $this->product?->name ?? "Product #{$this->product_id}";
                $warehouseName = $this->warehouse?->name ?? "Warehouse #{$this->warehouse_id}";

                if ($eventName === 'updated') {
                    $old = $this->oldAttributes['qty'] ?? null;

                    return "Stock for \"{$productName}\" at {$warehouseName} changed from {$old} to {$this->qty}";
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
}
