<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PackageProduct extends Pivot
{
    public $incrementing = true;

    protected $table = 'package_product';

    protected $fillable = ['package_id', 'product_id', 'qty', 'per_item_price'];

    protected $casts = [
        'per_item_price' => 'decimal:2',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sponsors(): HasMany
    {
        // Explicit FK: Pivot's AsPivot trait overrides getForeignKey() to
        // mean "the pivot's own FK into its parent belongsToMany" rather
        // than the usual auto-guessed FK, which would otherwise break
        // hasMany()'s default foreign-key guessing here.
        return $this->hasMany(PackageProductSponsor::class, 'package_product_id');
    }

    public function embellishments(): HasMany
    {
        return $this->hasMany(PackageProductEmbellishment::class, 'package_product_id');
    }
}
