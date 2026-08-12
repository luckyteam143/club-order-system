<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageProductSponsor extends Model
{
    protected $fillable = [
        'package_product_id', 'sponsor_logo_id', 'embellishment_position_id',
        'brochure_link', 'override_price',
    ];

    protected $casts = [
        'override_price' => 'decimal:2',
    ];

    public function packageProduct(): BelongsTo
    {
        return $this->belongsTo(PackageProduct::class);
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
