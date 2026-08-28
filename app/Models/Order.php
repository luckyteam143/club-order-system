<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'club_id', 'club_team_id', 'package_id', 'created_by', 'type', 'order_kind', 'status', 'is_copy', 'total', 'notes', 'submitted_at',
        'team_po', 'coach_manager', 'shipping_address', 'phone', 'email',
        'order_date', 'b2b_number', 'qb_invoice', 'brochure_link', 'last_changed_cells', 'forecast_season',
        'picking_status', 'picking_assigned_to', 'picking_sent_at', 'picking_completed_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'is_copy' => 'boolean',
        'submitted_at' => 'datetime',
        'order_date' => 'date',
        'last_changed_cells' => 'array',
        'picking_sent_at' => 'datetime',
        'picking_completed_at' => 'datetime',
    ];

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function clubTeam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClubTeam::class);
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function orderNotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderNote::class)->orderBy('created_at');
    }

    public function pickingAssignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'picking_assigned_to');
    }

    /**
     * Recomputes the order-level `picking_status` from every (item, size)
     * combination's own picked qty vs its ordered qty — picking is tracked
     * per size (sizes are separate barcoded/stocked catalog products), not
     * per item. A combo only counts toward "picked" once its full ordered
     * qty has been picked, so a single partially-picked size is enough to
     * make the whole order `partially_picked` even if every other size is
     * fully picked.
     */
    public function recalculatePickingStatus(): void
    {
        $this->loadMissing('orderItems.cells', 'orderItems.picks');

        $sizeCombos = $this->orderItems->flatMap(fn (OrderItem $item) => $item->sizeBreakdown()->keys()
            ->map(fn (string $size) => [$item, $size]));

        $allFull = $sizeCombos->isNotEmpty() && $sizeCombos->every(
            fn (array $combo) => $combo[0]->pickStateForSize($combo[1]) === 'full'
        );

        $anyPicked = $sizeCombos->contains(
            fn (array $combo) => $combo[0]->pickedQtyForSize($combo[1]) > 0
        );

        $this->update([
            'picking_status' => $allFull ? 'picked' : ($anyPicked ? 'partially_picked' : 'not_picked'),
        ]);
    }

    /**
     * Who order emails address and where they go — the club is the base
     * identity, but the linked team's coach/manager details take over
     * field-by-field wherever the team actually has that field filled in.
     */
    public function contactDetails(): array
    {
        $this->loadMissing(['club', 'clubTeam']);

        $club = $this->club;
        $team = $this->clubTeam;

        return [
            'name'    => filled($team?->coach_manager_name) ? $team->coach_manager_name : ($club?->contact_person ?: $club?->name),
            'email'   => filled($team?->coach_manager_email) ? $team->coach_manager_email : $club?->email,
            'phone'   => filled($team?->coach_manager_contact) ? $team->coach_manager_contact : $club?->phone,
            'address' => filled($team?->address) ? $team->address : $club?->address,
        ];
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
