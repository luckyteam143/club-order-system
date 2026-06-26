<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemCell extends Model
{
    protected $fillable = [
        'order_player_row_id', 'product_id', 'size', 'sponsor_logo_id',
        'sponsor_position', 'qty', 'unit_price', 'extra_cost', 'line_total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'extra_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function playerRow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderPlayerRow::class, 'order_player_row_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sponsorLogo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SponsorLogo::class);
    }

    public function recalculate(): void
    {
        $this->line_total = ($this->unit_price + $this->extra_cost) * $this->qty;
        $this->save();
    }
}
