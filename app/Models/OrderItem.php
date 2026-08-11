<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'sponsor_logo_id', 'embellishment_id', 'unit_price', 'sort_order',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sponsorLogo(): BelongsTo
    {
        return $this->belongsTo(SponsorLogo::class);
    }

    public function embellishment(): BelongsTo
    {
        return $this->belongsTo(Embellishment::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(OrderItemCell::class);
    }

    public function unitCost(): float
    {
        return (float) $this->unit_price
            + (float) ($this->embellishment?->cost ?? 0)
            + (float) ($this->sponsorLogo?->price ?? 0);
    }
}
