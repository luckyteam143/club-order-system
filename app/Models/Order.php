<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'club_id', 'package_id', 'type', 'status', 'total', 'notes', 'submitted_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function package(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function playerRows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderPlayerRow::class)->orderBy('player_index');
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order');
    }

    public function recalculateTotal(): void
    {
        $total = $this->orderItems()
            ->with('cells')
            ->get()
            ->sum(fn ($item) => $item->cells->sum('line_total'));

        $this->update(['total' => $total]);
    }
}
