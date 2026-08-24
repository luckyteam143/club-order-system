<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use LogsActivity;

    protected $fillable = [
        'barcode', 'parent_sku', 'default_sku', 'size', 'name',
        'status', 'macro_category', 'description', 'image_link', 'gallery_links',
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

    public function clubs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Club::class)->withTimestamps();
    }

    public function attributes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)->withTimestamps()
            ->orderBy('attributes.position')
            ->orderBy('attributes.name');
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function warehouseStocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }

    public function isParent(): bool
    {
        return blank($this->parent_sku);
    }

    /**
     * Keeps the flat `qty` column (used everywhere else — stock status,
     * exports, order pricing) in sync with the sum of this product's
     * per-warehouse stock, once it has any. Products with no warehouse
     * breakdown yet keep their existing manually-set `qty` untouched.
     */
    public function recalculateStock(): void
    {
        if (! $this->warehouseStocks()->exists()) {
            return;
        }

        $this->update(['qty' => (int) $this->warehouseStocks()->sum('qty')]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // qty is excluded — it's auto-synced from ProductWarehouseStock via
        // recalculateStock() on every stock movement, which already gets
        // its own log entry there; logging it here too would double up.
        return LogOptions::defaults()
            ->logOnly([
                'barcode', 'parent_sku', 'default_sku', 'size', 'name',
                'status', 'macro_category', 'description', 'image_link', 'gallery_links',
                'year', 'available_until_year', 'total_look', 'weight',
                'color1_code', 'color1_label', 'color2_code', 'color2_label',
                'co_sponsorship_id', 'on_backorder', 'backorder_date', 'retail_price',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('product')
            ->setDescriptionForEvent(fn (string $eventName) => "Product \"{$this->name}\" has been {$eventName}");
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
