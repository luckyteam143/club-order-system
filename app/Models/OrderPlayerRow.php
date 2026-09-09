<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPlayerRow extends Model
{
    protected $fillable = ['order_id', 'player_index', 'player_name', 'number', 'initials', 'notes', 'section'];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function itemCells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItemCell::class);
    }
}
