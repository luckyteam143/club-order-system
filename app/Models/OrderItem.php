<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'unit_price', 'sort_order',
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

    public function sponsors(): HasMany
    {
        return $this->hasMany(OrderItemSponsor::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(OrderItemCell::class);
    }

    public function unitCost(): float
    {
        return (float) $this->unit_price + (float) $this->sponsors->sum('price');
    }
}
