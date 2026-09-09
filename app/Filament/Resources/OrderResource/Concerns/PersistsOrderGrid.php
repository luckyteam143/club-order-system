<?php

namespace App\Filament\Resources\OrderResource\Concerns;

use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCell;
use App\Models\OrderItemEmbellishment;
use App\Models\OrderItemSponsor;
use App\Models\PackageProduct;
use App\Models\Product;
use App\Models\SponsorLogo;
use Illuminate\Support\Facades\DB;

trait PersistsOrderGrid
{
    protected ?array $gridState = null;

    protected function buildGridState(?Order $order): array
    {
        if (! $order || ! $order->exists) {
            return ['columns' => [], 'rows' => [], 'sponsors' => [], 'embellishments' => [], 'lastChangedCells' => []];
        }

        $order->loadMissing([
            'orderItems.cells',
            'orderItems.sponsors',
            'orderItems.embellishments',
            'playerRows.itemCells',
        ]);

        $columns = $order->orderItems->map(fn (OrderItem $item) => [
            'key' => 'i'.$item->id,
            'id' => $item->id,
            'product_id' => $item->product_id,
            'unit_price' => (float) $item->unit_price,
            'notes' => $item->notes,
            'has_club_crest' => (bool) $item->has_club_crest,
            'crest_number' => (int) ($item->crest_number ?? 1),
            'is_goalie_item' => (bool) $item->is_goalie_item,
            'is_player_item' => (bool) $item->is_player_item,
            'number_color' => $item->number_color,
        ])->values()->all();

        $sponsors = $order->orderItems->flatMap(fn (OrderItem $item) => $item->sponsors->map(fn ($sponsor) => [
            'key' => 'sp'.$sponsor->id,
            'id' => $sponsor->id,
            'item_key' => 'i'.$item->id,
            'sponsor_logo_id' => $sponsor->sponsor_logo_id,
            'embellishment_position_id' => $sponsor->embellishment_position_id,
            'brochure_link' => $sponsor->brochure_link,
            'override_price' => $sponsor->override_price,
        ]))->values()->all();

        $embellishments = $order->orderItems->flatMap(fn (OrderItem $item) => $item->embellishments->map(fn ($e) => [
            'key' => 'em'.$e->id,
            'id' => $e->id,
            'item_key' => 'i'.$item->id,
            'embellishment_id' => $e->embellishment_id,
            'embellishment_position_id' => $e->embellishment_position_id,
            'override_price' => $e->override_price,
        ]))->values()->all();

        $isBulk = in_array($order->order_kind, ['bulk', 'forecast'], true);

        $rows = $order->playerRows->map(function ($row) use ($isBulk) {
            $cells = [];

            if ($isBulk) {
                // One implicit row, qty per size per item instead of a
                // single size+qty pair — see persistBulkCells().
                foreach ($row->itemCells->groupBy('order_item_id') as $itemId => $itemCells) {
                    $cells['i'.$itemId] = ['sizes' => $itemCells->pluck('qty', 'size')->map(fn ($qty) => (int) $qty)->all()];
                }
            } else {
                foreach ($row->itemCells as $cell) {
                    $cells['i'.$cell->order_item_id] = ['size' => $cell->size, 'qty' => (int) $cell->qty];
                }
            }

            return [
                'key' => 'p'.$row->id,
                'id' => $row->id,
                'player_name' => $row->player_name,
                'number' => $row->number,
                'initials' => $row->initials,
                'notes' => $row->notes,
                'section' => $row->section ?? 'player',
                'cells' => $cells,
            ];
        })->values()->all();

        return [
            'columns' => $columns,
            'rows' => $rows,
            'sponsors' => $sponsors,
            'embellishments' => $embellishments,
            'lastChangedCells' => $order->last_changed_cells ?? [],
        ];
    }

    protected function persistGridState(Order $order, array $state): void
    {
        DB::transaction(function () use ($order, $state) {
            $columns = collect($state['columns'] ?? []);
            $rows = collect($state['rows'] ?? []);
            $sponsors = collect($state['sponsors'] ?? []);
            $embellishments = collect($state['embellishments'] ?? []);

            $columnKeyToId = [];
            $columnKeyToProductId = [];
            $keptItemIds = [];

            foreach ($columns as $sort => $col) {
                if (blank($col['product_id'] ?? null)) {
                    continue;
                }

                $hasCrest = (bool) ($col['has_club_crest'] ?? true);

                $attrs = [
                    'order_id' => $order->id,
                    'product_id' => $col['product_id'],
                    'sort_order' => $sort,
                    'notes' => blank($col['notes'] ?? null) ? null : $col['notes'],
                    'has_club_crest' => $hasCrest,
                    'crest_number' => $hasCrest ? max(1, (int) ($col['crest_number'] ?? 1)) : 1,
                    'is_goalie_item' => (bool) ($col['is_goalie_item'] ?? false),
                    'is_player_item' => (bool) ($col['is_player_item'] ?? true),
                    'number_color' => blank($col['number_color'] ?? null) ? null : trim((string) $col['number_color']),
                ];

                $id = $col['id'] ?? null;
                $existingItem = $id ? $order->orderItems()->whereKey($id)->first() : null;

                // Only an admin (manage_order_pricing) can set/change unit
                // price — club users see it but any value they submit here
                // is ignored, either keeping the item's current price or,
                // for a brand new item, falling back to whatever price is
                // actually defined for it in this order's context (package
                // kit price, club-negotiated price, or plain catalog price).
                $attrs['unit_price'] = $this->canEditOrderPricing()
                    ? ($col['unit_price'] ?? 0)
                    : ($existingItem?->unit_price ?? $this->resolveDefinedUnitPrice($order, (int) $col['product_id']));

                if ($existingItem) {
                    $existingItem->update($attrs);
                    $id = $existingItem->id;
                } else {
                    $id = $order->orderItems()->create($attrs)->id;
                }

                $columnKeyToId[$col['key']] = $id;
                $columnKeyToProductId[$col['key']] = (int) $col['product_id'];
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

                // A club user's submitted override is ignored: for an
                // existing sponsor row keep whatever override it already
                // had, and for a brand new one fall back to the override
                // this order's package defines for it (so a package's
                // predefined sponsor pricing still reaches the order) —
                // never a value the client made up.
                $overridePrice = $this->canEditOrderPricing()
                    ? $this->resolveOverridePrice($sponsor['override_price'] ?? null)
                    : (($sponsor['id'] ?? null)
                        ? OrderItemSponsor::find($sponsor['id'])?->override_price
                        : $this->resolvePackageAddOnOverride(
                            $order,
                            $columnKeyToProductId[$sponsor['item_key'] ?? null] ?? null,
                            'sponsors',
                            'sponsor_logo_id',
                            (int) $sponsorLogoId,
                            $positionId,
                        ));

                $attrs = [
                    'order_item_id' => $itemId,
                    'sponsor_logo_id' => $sponsorLogoId,
                    'embellishment_position_id' => $positionId,
                    'brochure_link' => blank($sponsor['brochure_link'] ?? null) ? null : $sponsor['brochure_link'],
                    'override_price' => $overridePrice,
                    'price' => $overridePrice ?? (float) $logo->price,
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

                // See the matching comment in the sponsors loop above.
                $overridePrice = $this->canEditOrderPricing()
                    ? $this->resolveOverridePrice($embellishment['override_price'] ?? null)
                    : (($embellishment['id'] ?? null)
                        ? OrderItemEmbellishment::find($embellishment['id'])?->override_price
                        : $this->resolvePackageAddOnOverride(
                            $order,
                            $columnKeyToProductId[$embellishment['item_key'] ?? null] ?? null,
                            'embellishments',
                            'embellishment_id',
                            (int) $embellishmentId,
                            $positionId,
                        ));

                $attrs = [
                    'order_item_id' => $itemId,
                    'embellishment_id' => $embellishmentId,
                    'embellishment_position_id' => $positionId,
                    'override_price' => $overridePrice,
                    'price' => $overridePrice ?? (float) $catalogEmbellishment->cost,
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

            if (in_array($order->order_kind, ['bulk', 'forecast'], true)) {
                $this->persistBulkCells($order, $rows->first() ?? [], $columnKeyToId, $items);
                $order->recalculateTotal();

                return;
            }

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
                    'order_id' => $order->id,
                    'player_index' => $index,
                    'player_name' => $row['player_name'] ?? null,
                    'number' => $row['number'] ?? null,
                    'initials' => $row['initials'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'section' => ($row['section'] ?? 'player') === 'goalie' ? 'goalie' : 'player',
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
                        'order_item_id' => $item->id,
                        'order_player_row_id' => $id,
                        'size' => $cell['size'],
                        'qty' => $qty,
                        'line_total' => $item->unitCost() * $qty,
                    ]);
                }
            }

            $order->playerRows()->whereNotIn('id', $keptRowIds ?: [0])->delete();

            $order->recalculateTotal();
        });
    }

    /**
     * Bulk Order / Forecast has no named players — everything lives on one
     * implicit OrderPlayerRow (created here if it doesn't exist yet, and
     * any extras pruned defensively even though the client only ever sends
     * one), with a qty-per-size OrderItemCell per item instead of a single
     * size+qty cell per (player, item) pair.
     *
     * @param  array<string, int>  $columnKeyToId
     */
    private function persistBulkCells(Order $order, array $rowState, array $columnKeyToId, $items): void
    {
        $attrs = [
            'order_id' => $order->id,
            'player_index' => 0,
            'player_name' => null,
            'number' => null,
            'initials' => null,
            'notes' => null,
        ];

        $id = $rowState['id'] ?? null;
        $row = $id ? $order->playerRows()->whereKey($id)->first() : null;
        $row ??= $order->playerRows()->first();

        if ($row) {
            $row->update($attrs);
            $id = $row->id;
        } else {
            $id = $order->playerRows()->create($attrs)->id;
        }

        $order->playerRows()->where('id', '!=', $id)->delete();

        OrderItemCell::where('order_player_row_id', $id)->delete();

        foreach (($rowState['cells'] ?? []) as $colKey => $cell) {
            $itemId = $columnKeyToId[$colKey] ?? null;
            $item = $itemId ? $items->get($itemId) : null;

            if (! $item) {
                continue;
            }

            foreach (($cell['sizes'] ?? []) as $size => $qty) {
                $qty = max(0, (int) $qty);

                if ($qty <= 0 || blank($size)) {
                    continue;
                }

                OrderItemCell::create([
                    'order_item_id' => $item->id,
                    'order_player_row_id' => $id,
                    'size' => $size,
                    'qty' => $qty,
                    'line_total' => $item->unitCost() * $qty,
                ]);
            }
        }
    }

    /**
     * The client-side price inputs are already disabled for anyone without
     * this permission, but that's UX only — a crafted Livewire payload
     * could still submit different numbers, so every price field is
     * re-checked against this server-side before it's persisted.
     */
    private function canEditOrderPricing(): bool
    {
        return auth()->user()?->can('manage_order_pricing') ?? false;
    }

    /**
     * The price a brand new item should get when the submitting user can't
     * set one themselves — sourced from wherever this order's type actually
     * defines it: a package's per-item kit price, a club's Online Store
     * Price for club-items orders, or the product's plain catalog price for
     * individual orders (also the fallback if no package/club price is set).
     */
    private function resolveDefinedUnitPrice(Order $order, int $productId): float
    {
        if ($order->type === 'package' && $order->package_id) {
            $pivotPrice = $order->package?->products()
                ->where('products.id', $productId)
                ->first()?->pivot->per_item_price;

            if ($pivotPrice !== null) {
                return (float) $pivotPrice;
            }
        }

        if ($order->type === 'club_items' && $order->club_id) {
            // The grid auto-fills from this same field (ClubResource's
            // "Assigned Items > Online Store Price") — see clubItemsCatalogForGrid().
            $pivotPrice = $order->club?->products()
                ->where('products.id', $productId)
                ->first()?->pivot->online_store_price;

            if ($pivotPrice !== null) {
                return (float) $pivotPrice;
            }
        }

        return (float) (Product::find($productId)?->retail_price ?? 0);
    }

    /**
     * The override price this order's package predefines for a sponsor
     * logo / embellishment on one of its items — used when the submitting
     * user can't set prices themselves and the add-on row is brand new
     * (seeded from the package), so a package's predefined add-on pricing
     * still reaches the order instead of silently reverting to catalog.
     * Null for non-package orders, an unknown item, or no override set.
     *
     * @param  'sponsors'|'embellishments'  $relation
     * @param  'sponsor_logo_id'|'embellishment_id'  $matchColumn
     */
    private function resolvePackageAddOnOverride(Order $order, ?int $productId, string $relation, string $matchColumn, int $matchId, ?int $positionId): ?float
    {
        if ($order->type !== 'package' || ! $order->package_id || ! $productId || $matchId <= 0) {
            return null;
        }

        $packageProduct = PackageProduct::query()
            ->where('package_id', $order->package_id)
            ->where('product_id', $productId)
            // The same product can be in a package more than once; its
            // add-ons live on the last row (see PersistsPackageItems).
            ->orderByDesc('sort_order')
            ->first();

        if (! $packageProduct) {
            return null;
        }

        $match = $packageProduct->{$relation}()
            ->where($matchColumn, $matchId)
            ->where('embellishment_position_id', $positionId)
            ->first()
            ?? $packageProduct->{$relation}()
                ->where($matchColumn, $matchId)
                ->first();

        return $match?->override_price === null ? null : (float) $match->override_price;
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
