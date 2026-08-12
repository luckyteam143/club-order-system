<?php

namespace App\Filament\Resources\OrderResource\Concerns;

use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCell;
use App\Models\OrderItemEmbellishment;
use App\Models\OrderItemSponsor;
use App\Models\SponsorLogo;
use Illuminate\Support\Facades\DB;

trait PersistsOrderGrid
{
    protected ?array $gridState = null;

    protected function buildGridState(?Order $order): array
    {
        if (! $order || ! $order->exists) {
            return ['columns' => [], 'rows' => [], 'sponsors' => [], 'embellishments' => []];
        }

        $order->loadMissing([
            'orderItems.cells',
            'orderItems.sponsors',
            'orderItems.embellishments',
            'playerRows.itemCells',
        ]);

        $columns = $order->orderItems->map(fn (OrderItem $item) => [
            'key'         => 'i'.$item->id,
            'id'          => $item->id,
            'product_id'  => $item->product_id,
            'unit_price'  => (float) $item->unit_price,
        ])->values()->all();

        $sponsors = $order->orderItems->flatMap(fn (OrderItem $item) => $item->sponsors->map(fn ($sponsor) => [
            'key'                       => 'sp'.$sponsor->id,
            'id'                        => $sponsor->id,
            'item_key'                  => 'i'.$item->id,
            'sponsor_logo_id'           => $sponsor->sponsor_logo_id,
            'embellishment_position_id' => $sponsor->embellishment_position_id,
            'brochure_link'             => $sponsor->brochure_link,
            'override_price'            => $sponsor->override_price,
        ]))->values()->all();

        $embellishments = $order->orderItems->flatMap(fn (OrderItem $item) => $item->embellishments->map(fn ($e) => [
            'key'                       => 'em'.$e->id,
            'id'                        => $e->id,
            'item_key'                  => 'i'.$item->id,
            'embellishment_id'          => $e->embellishment_id,
            'embellishment_position_id' => $e->embellishment_position_id,
            'override_price'            => $e->override_price,
        ]))->values()->all();

        $rows = $order->playerRows->map(function ($row) {
            $cells = [];
            foreach ($row->itemCells as $cell) {
                $cells['i'.$cell->order_item_id] = ['size' => $cell->size, 'qty' => (int) $cell->qty];
            }

            return [
                'key'         => 'p'.$row->id,
                'id'          => $row->id,
                'player_name' => $row->player_name,
                'number'      => $row->number,
                'initials'    => $row->initials,
                'notes'       => $row->notes,
                'cells'       => $cells,
            ];
        })->values()->all();

        return ['columns' => $columns, 'rows' => $rows, 'sponsors' => $sponsors, 'embellishments' => $embellishments];
    }

    protected function persistGridState(Order $order, array $state): void
    {
        DB::transaction(function () use ($order, $state) {
            $columns = collect($state['columns'] ?? []);
            $rows = collect($state['rows'] ?? []);
            $sponsors = collect($state['sponsors'] ?? []);
            $embellishments = collect($state['embellishments'] ?? []);

            $columnKeyToId = [];
            $keptItemIds = [];

            foreach ($columns as $sort => $col) {
                if (blank($col['product_id'] ?? null)) {
                    continue;
                }

                $attrs = [
                    'order_id'    => $order->id,
                    'product_id'  => $col['product_id'],
                    'unit_price'  => $col['unit_price'] ?? 0,
                    'sort_order'  => $sort,
                ];

                $id = $col['id'] ?? null;
                if ($id && $order->orderItems()->whereKey($id)->exists()) {
                    $order->orderItems()->whereKey($id)->update($attrs);
                } else {
                    $id = $order->orderItems()->create($attrs)->id;
                }

                $columnKeyToId[$col['key']] = $id;
                $keptItemIds[] = $id;
            }

            $order->orderItems()->whereNotIn('id', $keptItemIds ?: [0])->delete();

            $keptSponsorIds = [];

            foreach ($sponsors as $sponsor) {
                $itemId = $columnKeyToId[$sponsor['item_key'] ?? null] ?? null;
                $sponsorLogoId = $sponsor['sponsor_logo_id'] ?? null;
                if (! $itemId || blank($sponsorLogoId) || (int) $sponsorLogoId <= 0) {
                    continue;
                }

                $logo = SponsorLogo::find($sponsorLogoId);
                if (! $logo) {
                    // Stale/unknown logo id — skip rather than let an invalid
                    // foreign key roll back everything else in this save.
                    continue;
                }

                $positionId = $this->resolvePositionId($sponsor['embellishment_position_id'] ?? null);
                $overridePrice = $this->resolveOverridePrice($sponsor['override_price'] ?? null);

                $attrs = [
                    'order_item_id'              => $itemId,
                    'sponsor_logo_id'            => $sponsorLogoId,
                    'embellishment_position_id'  => $positionId,
                    'brochure_link'              => blank($sponsor['brochure_link'] ?? null) ? null : $sponsor['brochure_link'],
                    'override_price'             => $overridePrice,
                    'price'                      => $overridePrice ?? (float) $logo->price,
                ];

                $id = $sponsor['id'] ?? null;
                if ($id && OrderItemSponsor::whereKey($id)->whereIn('order_item_id', $keptItemIds)->exists()) {
                    OrderItemSponsor::whereKey($id)->update($attrs);
                } else {
                    $id = OrderItemSponsor::create($attrs)->id;
                }

                $keptSponsorIds[] = $id;
            }

            OrderItemSponsor::whereIn('order_item_id', $keptItemIds ?: [0])
                ->whereNotIn('id', $keptSponsorIds ?: [0])
                ->delete();

            $keptEmbellishmentIds = [];

            foreach ($embellishments as $embellishment) {
                $itemId = $columnKeyToId[$embellishment['item_key'] ?? null] ?? null;
                $embellishmentId = $embellishment['embellishment_id'] ?? null;
                if (! $itemId || blank($embellishmentId) || (int) $embellishmentId <= 0) {
                    continue;
                }

                $catalogEmbellishment = Embellishment::find($embellishmentId);
                if (! $catalogEmbellishment) {
                    continue;
                }

                $positionId = $this->resolvePositionId($embellishment['embellishment_position_id'] ?? null);
                $overridePrice = $this->resolveOverridePrice($embellishment['override_price'] ?? null);

                $attrs = [
                    'order_item_id'              => $itemId,
                    'embellishment_id'           => $embellishmentId,
                    'embellishment_position_id'  => $positionId,
                    'override_price'             => $overridePrice,
                    'price'                      => $overridePrice ?? (float) $catalogEmbellishment->cost,
                ];

                $id = $embellishment['id'] ?? null;
                if ($id && OrderItemEmbellishment::whereKey($id)->whereIn('order_item_id', $keptItemIds)->exists()) {
                    OrderItemEmbellishment::whereKey($id)->update($attrs);
                } else {
                    $id = OrderItemEmbellishment::create($attrs)->id;
                }

                $keptEmbellishmentIds[] = $id;
            }

            OrderItemEmbellishment::whereIn('order_item_id', $keptItemIds ?: [0])
                ->whereNotIn('id', $keptEmbellishmentIds ?: [0])
                ->delete();

            $items = $order->orderItems()->with(['sponsors', 'embellishments'])->whereIn('id', $keptItemIds ?: [0])->get()->keyBy('id');

            $keptRowIds = [];

            foreach ($rows as $index => $row) {
                $hasContent = filled($row['player_name'] ?? null)
                    || filled($row['number'] ?? null)
                    || filled($row['initials'] ?? null)
                    || filled($row['notes'] ?? null)
                    || collect($row['cells'] ?? [])->contains(fn ($c) => filled($c['size'] ?? null));

                if (! $hasContent) {
                    continue;
                }

                $attrs = [
                    'order_id'     => $order->id,
                    'player_index' => $index,
                    'player_name'  => $row['player_name'] ?? null,
                    'number'       => $row['number'] ?? null,
                    'initials'     => $row['initials'] ?? null,
                    'notes'        => $row['notes'] ?? null,
                ];

                $id = $row['id'] ?? null;
                if ($id && $order->playerRows()->whereKey($id)->exists()) {
                    $order->playerRows()->whereKey($id)->update($attrs);
                } else {
                    $id = $order->playerRows()->create($attrs)->id;
                }

                $keptRowIds[] = $id;

                OrderItemCell::where('order_player_row_id', $id)->delete();

                foreach (($row['cells'] ?? []) as $colKey => $cell) {
                    if (blank($cell['size'] ?? null)) {
                        continue;
                    }

                    $itemId = $columnKeyToId[$colKey] ?? null;
                    $item = $itemId ? $items->get($itemId) : null;
                    if (! $item) {
                        continue;
                    }

                    $qty = max(1, (int) ($cell['qty'] ?? 1));

                    OrderItemCell::create([
                        'order_item_id'        => $item->id,
                        'order_player_row_id'  => $id,
                        'size'                 => $cell['size'],
                        'qty'                  => $qty,
                        'line_total'           => $item->unitCost() * $qty,
                    ]);
                }
            }

            $order->playerRows()->whereNotIn('id', $keptRowIds ?: [0])->delete();

            $order->recalculateTotal();
        });
    }

    /**
     * Coerces a client-supplied position id to a real, existing
     * EmbellishmentPosition id, or null if it's blank/invalid — so a stale
     * or empty id can never trip a foreign key constraint.
     */
    private function resolvePositionId(mixed $rawPositionId): ?int
    {
        $positionId = (int) ($rawPositionId ?? 0);

        if ($positionId <= 0 || ! EmbellishmentPosition::whereKey($positionId)->exists()) {
            return null;
        }

        return $positionId;
    }

    /**
     * A blank override means "use the catalog price" — anything else is
     * coerced to a float so it can replace it outright.
     */
    private function resolveOverridePrice(mixed $rawOverridePrice): ?float
    {
        // blank() correctly treats 0 / "0" as real (non-blank) values, so
        // an override of exactly zero is preserved rather than discarded.
        return blank($rawOverridePrice) ? null : (float) $rawOverridePrice;
    }
}
