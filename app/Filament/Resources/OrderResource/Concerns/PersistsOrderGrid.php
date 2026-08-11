<?php

namespace App\Filament\Resources\OrderResource\Concerns;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCell;
use App\Models\OrderItemSponsor;
use App\Models\SponsorLogo;
use Illuminate\Support\Facades\DB;

trait PersistsOrderGrid
{
    protected ?array $gridState = null;

    protected function buildGridState(?Order $order): array
    {
        if (! $order || ! $order->exists) {
            return ['columns' => [], 'rows' => [], 'sponsors' => []];
        }

        $order->loadMissing(['orderItems.cells', 'orderItems.sponsors', 'playerRows.itemCells']);

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

        return ['columns' => $columns, 'rows' => $rows, 'sponsors' => $sponsors];
    }

    protected function persistGridState(Order $order, array $state): void
    {
        DB::transaction(function () use ($order, $state) {
            $columns = collect($state['columns'] ?? []);
            $rows = collect($state['rows'] ?? []);
            $sponsors = collect($state['sponsors'] ?? []);

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
                if (! $itemId || blank($sponsorLogoId)) {
                    continue;
                }

                $price = (float) (SponsorLogo::find($sponsorLogoId)?->price ?? 0);

                $attrs = [
                    'order_item_id'              => $itemId,
                    'sponsor_logo_id'            => $sponsorLogoId,
                    'embellishment_position_id'  => $sponsor['embellishment_position_id'] ?: null,
                    'price'                      => $price,
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

            $items = $order->orderItems()->with('sponsors')->whereIn('id', $keptItemIds ?: [0])->get()->keyBy('id');

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
}
