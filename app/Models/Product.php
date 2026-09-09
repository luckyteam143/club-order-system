<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function coSponsorship(): BelongsTo
    {
        return $this->belongsTo(CoSponsorship::class);
    }

    /**
     * The parent/master product this size variant belongs to, linked by
     * `parent_sku` = the parent's own `default_sku` (see the FK added in
     * 2026_08_31_130000). NULL on a parent product.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_sku', 'default_sku');
    }

    /** Size variants that point at this product's `default_sku`. */
    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_sku', 'default_sku');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class)->withPivot(['qty', 'per_item_price'])->withTimestamps();
    }

    public function clubs(): BelongsToMany
    {
        // Mirror Club::products() — the club_product pivot carries this
        // club's own pricing and crest settings for the product.
        return $this->belongsToMany(Club::class)
            ->withPivot(['id', 'club_price', 'online_store_price', 'has_club_crest', 'crest_number'])
            ->withTimestamps();
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)->withTimestamps()
            ->orderBy('attributes.position')
            ->orderBy('attributes.name');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function warehouseStocks(): HasMany
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
        if ($this->qty === 0 && ! $this->on_backorder) {
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
