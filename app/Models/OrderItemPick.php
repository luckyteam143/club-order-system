<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per (order item, size) actually worked on the picking page — sizes are separate barcoded/stocked products, so picking is tracked at this granularity, not per item. */
class OrderItemPick extends Model
{
    protected $fillable = [
        'order_item_id', 'size', 'picked_qty', 'picking_note', 'picked_by', 'picked_at',
    ];

    protected $casts = [
        'picked_at' => 'datetime',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function pickedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }
}
