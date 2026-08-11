<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorLogo extends Model
{
    protected $fillable = ['club_id', 'name', 'file', 'embellishment_position_id', 'price'];

    protected $casts = ['price' => 'decimal:2'];

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EmbellishmentPosition::class, 'embellishment_position_id');
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
