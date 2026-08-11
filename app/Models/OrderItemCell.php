<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemCell extends Model
{
    protected $fillable = [
        'order_item_id', 'order_player_row_id', 'size', 'qty', 'line_total',
    ];

    protected $casts = [
        'line_total' => 'decimal:2',
    ];

    public function orderItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function playerRow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderPlayerRow::class, 'order_player_row_id');
    }

    public function recalculate(): void
    {
        $this->line_total = $this->orderItem->unitCost() * $this->qty;
        $this->save();
    }
}
