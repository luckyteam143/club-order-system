<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'unit_price', 'sort_order', 'notes', 'has_club_crest', 'crest_number',
        'is_goalie_item', 'is_player_item', 'number_color',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'has_club_crest' => 'boolean',
        'crest_number' => 'integer',
        'is_goalie_item' => 'boolean',
        'is_player_item' => 'boolean',
    ];

    /** Cached across every OrderItem instance for the life of the request — avoids re-querying Attribute on every item in a loop. */
    private static ?Collection $sizeOrderCache = null;

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

    public function embellishments(): HasMany
    {
        return $this->hasMany(OrderItemEmbellishment::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(OrderItemCell::class);
    }

    /** One row per size actually worked on the picking page — see OrderItemPick. */
    public function picks(): HasMany
    {
        return $this->hasMany(OrderItemPick::class);
    }

    public function unitCost(): float
    {
        return (float) $this->unit_price
            + (float) $this->sponsors->sum('price')
            + (float) $this->embellishments->sum('price');
    }

    public function orderedQty(): int
    {
        return (int) $this->cells->sum('qty');
    }

    /** Smallest-to-largest by Attribute position, not alphabetically — same ordering used everywhere else (Add/Edit grid, order view, pick list). */
    public static function sizeOrder(): Collection
    {
        return static::$sizeOrderCache ??= Attribute::orderBy('position')->orderBy('name')->pluck('name')->flip();
    }

    /** @return Collection<string, int> size => ordered qty, ordered by the app's standard size position. */
    public function sizeBreakdown(): Collection
    {
        $sizeOrder = static::sizeOrder();

        return $this->cells
            ->filter(fn (OrderItemCell $cell) => $cell->size && $cell->qty > 0)
            ->sort(fn ($a, $b) => [$sizeOrder[$a->size] ?? PHP_INT_MAX, $a->size] <=> [$sizeOrder[$b->size] ?? PHP_INT_MAX, $b->size])
            ->groupBy('size')
            ->map(fn (Collection $cells) => (int) $cells->sum('qty'));
    }

    public function orderedQtyForSize(string $size): int
    {
        return (int) $this->cells->where('size', $size)->sum('qty');
    }

    public function pickFor(string $size): ?OrderItemPick
    {
        return $this->picks->firstWhere('size', $size);
    }

    public function pickedQtyForSize(string $size): int
    {
        return $this->pickFor($size)?->picked_qty ?? 0;
    }

    public function balanceForSize(string $size): int
    {
        return max(0, $this->orderedQtyForSize($size) - $this->pickedQtyForSize($size));
    }

    /** 'full' | 'partial' | 'none' for one size — drives the admin-side pick-status highlighting. */
    public function pickStateForSize(string $size): string
    {
        $ordered = $this->orderedQtyForSize($size);

        if ($ordered <= 0) {
            return 'none';
        }

        $picked = $this->pickedQtyForSize($size);

        if ($picked >= $ordered) {
            return 'full';
        }

        return $picked > 0 ? 'partial' : 'none';
    }

    /** Whole-item totals across every size — for admin summaries only; picking itself always operates per size. */
    public function pickedQtyTotal(): int
    {
        return (int) $this->picks->sum('picked_qty');
    }

    public function balanceQtyTotal(): int
    {
        return max(0, $this->orderedQty() - $this->pickedQtyTotal());
    }

    /**
     * The actual barcoded/stocked catalog product for one size of this
     * item — the item's own `product` is the parent style (no size, used
     * only for order pricing/roster), so picking must resolve the size's
     * own variant to know its real barcode and warehouse stock. Variants
     * are matched by `parent_sku` = the parent's own `default_sku`, same
     * relationship the Stock resource's search already relies on.
     */
    public function resolveSizeVariant(string $size): ?Product
    {
        if (! $this->product?->default_sku) {
            return null;
        }

        return Product::where('parent_sku', $this->product->default_sku)
            ->where('size', $size)
            ->first();
    }
}
