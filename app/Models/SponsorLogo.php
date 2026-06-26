<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorLogo extends Model
{
    protected $fillable = ['name', 'file', 'positions'];

    protected $casts = ['positions' => 'array'];

    public function orderItemCells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItemCell::class);
    }
}
