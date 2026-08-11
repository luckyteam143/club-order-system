<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'barcode', 'parent_sku', 'default_sku', 'size', 'name',
        'status', 'description', 'image_link', 'gallery_links',
        'year', 'available_until_year', 'total_look', 'weight',
        'color1_code', 'color1_label', 'color2_code', 'color2_label',
        'co_sponsorship_id',
        'qty', 'on_backorder', 'backorder_date', 'retail_price',
    ];

    protected $casts = [
        'on_backorder' => 'boolean',
        'backorder_date' => 'date',
        'retail_price' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    public function coSponsorship(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CoSponsorship::class);
    }

    public function packages(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Package::class)->withPivot(['qty', 'per_item_price'])->withTimestamps();
    }

    public function attributes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)->withTimestamps();
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isParent(): bool
    {
        return blank($this->parent_sku);
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
