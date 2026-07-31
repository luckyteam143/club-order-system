<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorLogo extends Model
{
    protected $fillable = ['club_id', 'name', 'file', 'positions'];

    protected $casts = ['positions' => 'array'];

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function orderItemCells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItemCell::class);
    }
}
