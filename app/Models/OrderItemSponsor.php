<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemSponsor extends Model
{
    protected $fillable = [
        'order_item_id', 'sponsor_logo_id', 'embellishment_position_id', 'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function sponsorLogo(): BelongsTo
    {
        return $this->belongsTo(SponsorLogo::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(EmbellishmentPosition::class, 'embellishment_position_id');
    }
}
