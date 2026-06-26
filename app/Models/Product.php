<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'barcode', 'parent_sku', 'default_sku', 'size', 'name',
        'qty', 'on_backorder', 'backorder_date', 'retail_price',
    ];

    protected $casts = [
        'on_backorder' => 'boolean',
        'backorder_date' => 'date',
        'retail_price' => 'decimal:2',
    ];

    public function packages(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Package::class)->withPivot(['qty', 'per_item_price'])->withTimestamps();
    }

    public function orderItemCells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItemCell::class);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->qty === 0 && !$this->on_backorder) {
            return 'out_of_stock';
        }
        if ($this->qty <= 5 && $this->qty > 0) {
            return 'low_stock';
        }
        if ($this->on_backorder) {
            return 'backorder';
        }
        return 'in_stock';
    }
}
