<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageProductEmbellishment extends Model
{
    protected $fillable = [
        'package_product_id', 'embellishment_id', 'embellishment_position_id', 'override_price',
    ];

    protected $casts = [
        'override_price' => 'decimal:2',
    ];

    public function packageProduct(): BelongsTo
    {
        return $this->belongsTo(PackageProduct::class);
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
