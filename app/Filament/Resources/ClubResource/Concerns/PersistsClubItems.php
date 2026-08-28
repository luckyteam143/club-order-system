<?php

namespace App\Filament\Resources\ClubResource\Concerns;

use App\Models\Club;

/**
 * Manages the club_product pivot manually instead of via Filament's
 * Repeater::relationship() — see PackageResource\Concerns\PersistsPackageItems
 * for why that pattern breaks for a "pick an existing record + edit pivot
 * data" repeater.
 */
trait PersistsClubItems
{
    protected ?array $clubItems = null;

    protected function buildItemsState(?Club $club): array
    {
        if (! $club || ! $club->exists) {
            return [];
        }

        return $club->products->map(fn ($product) => [
            'product_id'         => $product->id,
            'club_price'         => $product->pivot->club_price,
            'online_store_price' => $product->pivot->online_store_price,
            'has_club_crest'     => (bool) $product->pivot->has_club_crest,
            'crest_number'       => (int) ($product->pivot->crest_number ?? 1),
        ])->values()->all();
    }

    protected function persistItems(Club $club, array $items): void
    {
        $sync = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;

            if (blank($productId)) {
                continue;
            }

            $hasCrest = (bool) ($item['has_club_crest'] ?? true);

            $sync[(int) $productId] = [
                'club_price'         => filled($item['club_price'] ?? null) ? (float) $item['club_price'] : null,
                'online_store_price' => filled($item['online_store_price'] ?? null) ? (float) $item['online_store_price'] : null,
                'has_club_crest'     => $hasCrest,
                'crest_number'       => $hasCrest ? max(1, (int) ($item['crest_number'] ?? 1)) : 1,
            ];
        }

        $club->products()->sync($sync);
    }
}
