<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemEmbellishment extends Model
{
    protected $fillable = [
        'order_item_id', 'embellishment_id', 'embellishment_position_id', 'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function embellishment(): BelongsTo
    {
        return $this->belongsTo(Embellishment::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(EmbellishmentPosition::class, 'embellishment_position_id');
    }
}
