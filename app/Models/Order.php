<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'club_id', 'package_id', 'type', 'status', 'total', 'notes', 'submitted_at',
        'team_po', 'coach_manager', 'shipping_address', 'phone', 'email',
        'order_date', 'b2b_number', 'qb_invoice', 'brochure_link',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'submitted_at' => 'datetime',
        'order_date' => 'date',
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
        // Package orders with a defined kit price charge that flat price per
        // participating player, instead of summing each item's own unit
        // price — sponsor/embellishment add-ons still apply on top per item.
        if ($this->type === 'package') {
            $this->loadMissing('package');
            $kitPrice = (float) ($this->package?->price ?? 0);

            if ($kitPrice > 0) {
                $items = $this->orderItems()->with(['cells', 'sponsors', 'embellishments'])->get();

                $participatingRows = $items
                    ->flatMap(fn (OrderItem $item) => $item->cells)
                    ->pluck('order_player_row_id')
                    ->unique()
                    ->count();

                $addOnsTotal = $items->sum(function (OrderItem $item) {
                    $addOnUnit = (float) $item->sponsors->sum('price') + (float) $item->embellishments->sum('price');

                    return $item->cells->sum(fn (OrderItemCell $cell) => $addOnUnit * $cell->qty);
                });

                $this->update(['total' => ($participatingRows * $kitPrice) + $addOnsTotal]);

                return;
            }
        }

        $total = $this->orderItems()
            ->with('cells')
            ->get()
            ->sum(fn ($item) => $item->cells->sum('line_total'));

        $this->update(['total' => $total]);
    }
}
