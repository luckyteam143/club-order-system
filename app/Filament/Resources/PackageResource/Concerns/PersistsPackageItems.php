<?php

namespace App\Filament\Resources\PackageResource\Concerns;

use App\Models\Package;
use App\Models\PackageProduct;
use App\Models\PackageProductEmbellishment;
use App\Models\PackageProductSponsor;
use Illuminate\Support\Facades\DB;

/**
 * Manages the package_product pivot (and its own sponsor/embellishment
 * child rows) manually instead of via Filament's Repeater::relationship() —
 * that pattern treats every row without an already-hydrated related record
 * as a brand new model to *create*, which tried to insert a blank Product
 * row here.
 *
 * Sponsor Logos / Embellishments are their own top-level repeaters (mirrors
 * the Order page's layout) rather than nested under each item, so each row
 * references its item by product_id and gets resolved to the matching
 * package_product row at save time. When the same product appears in more
 * than one item row, a sponsor/embellishment targeting it applies to
 * whichever of those rows is processed last — the same product can be
 * listed multiple times (e.g. different qty/price), but its sponsor/
 * embellishment assignments aren't tracked per individual row.
 */
trait PersistsPackageItems
{
    protected ?array $packageItems = null;
    protected ?array $packageSponsors = null;
    protected ?array $packageEmbellishments = null;

    protected function buildItemsState(?Package $package): array
    {
        if (! $package || ! $package->exists) {
            return [];
        }

        return $package->packageProducts()->orderBy('sort_order')->get()
            ->map(fn (PackageProduct $packageProduct) => [
                'id'             => $packageProduct->id,
                'product_id'     => $packageProduct->product_id,
                'qty'            => $packageProduct->qty,
                'per_item_price' => $packageProduct->per_item_price,
                'has_club_crest' => (bool) $packageProduct->has_club_crest,
                'crest_number'   => (int) ($packageProduct->crest_number ?? 1),
            ])
            ->values()
            ->all();
    }

    protected function buildSponsorsState(?Package $package): array
    {
        if (! $package || ! $package->exists) {
            return [];
        }

        return $package->packageProducts()->with('sponsors')->get()
            ->flatMap(fn (PackageProduct $packageProduct) => $packageProduct->sponsors->map(fn (PackageProductSponsor $s) => [
                'product_id'                => $packageProduct->product_id,
                'sponsor_logo_id'           => $s->sponsor_logo_id,
                'embellishment_position_id' => $s->embellishment_position_id,
                'brochure_link'             => $s->brochure_link,
                'override_price'            => $s->override_price,
            ]))
            ->values()
            ->all();
    }

    protected function buildEmbellishmentsState(?Package $package): array
    {
        if (! $package || ! $package->exists) {
            return [];
        }

        return $package->packageProducts()->with('embellishments')->get()
            ->flatMap(fn (PackageProduct $packageProduct) => $packageProduct->embellishments->map(fn (PackageProductEmbellishment $e) => [
                'product_id'                => $packageProduct->product_id,
                'embellishment_id'          => $e->embellishment_id,
                'embellishment_position_id' => $e->embellishment_position_id,
                'override_price'            => $e->override_price,
            ]))
            ->values()
            ->all();
    }

    protected function persistItems(Package $package, array $items, array $sponsors = [], array $embellishments = []): void
    {
        DB::transaction(function () use ($package, $items, $sponsors, $embellishments) {
            $keptPackageProductIds = [];
            $productIdToPackageProductId = [];

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;

                if (blank($productId)) {
                    continue;
                }

                $hasCrest = (bool) ($item['has_club_crest'] ?? true);

                $attrs = [
                    'package_id'     => $package->id,
                    'product_id'     => (int) $productId,
                    // The Repeater's drag-and-drop reordering only changes
                    // its array position — this is what actually persists
                    // that order across page loads.
                    'sort_order'     => $index,
                    'qty'            => max(1, (int) ($item['qty'] ?? 1)),
                    'per_item_price' => filled($item['per_item_price'] ?? null) ? (float) $item['per_item_price'] : null,
                    'has_club_crest' => $hasCrest,
                    'crest_number'   => $hasCrest ? max(1, (int) ($item['crest_number'] ?? 1)) : 1,
                ];

                // Matched by row id (not product_id) so the same product can
                // be added as more than one item row instead of the second
                // occurrence silently overwriting the first.
                $id = $item['id'] ?? null;
                $packageProduct = $id ? PackageProduct::where('package_id', $package->id)->whereKey($id)->first() : null;

                if ($packageProduct) {
                    $packageProduct->update($attrs);
                } else {
                    $packageProduct = PackageProduct::create($attrs);
                }

                $keptPackageProductIds[] = $packageProduct->id;
                // Last row for a given product wins the sponsor/embellishment mapping.
                $productIdToPackageProductId[(int) $productId] = $packageProduct->id;
            }

            PackageProduct::where('package_id', $package->id)
                ->whereNotIn('id', $keptPackageProductIds ?: [0])
                ->get()
                ->each(fn (PackageProduct $packageProduct) => $packageProduct->delete());

            $this->persistSponsors($productIdToPackageProductId, $sponsors);
            $this->persistEmbellishments($productIdToPackageProductId, $embellishments);
        });
    }

    private function persistSponsors(array $productIdToPackageProductId, array $sponsors): void
    {
        $packageProductIds = array_values($productIdToPackageProductId);

        // Rows carry no stable identity of their own across separate saves
        // (they're keyed by product_id, resolved fresh each time) — clear
        // and recreate rather than diff/upsert.
        PackageProductSponsor::whereIn('package_product_id', $packageProductIds ?: [0])->delete();

        foreach ($sponsors as $sponsor) {
            $productId = (int) ($sponsor['product_id'] ?? 0);
            $packageProductId = $productIdToPackageProductId[$productId] ?? null;
            $sponsorLogoId = $sponsor['sponsor_logo_id'] ?? null;

            if (! $packageProductId || blank($sponsorLogoId)) {
                continue;
            }

            PackageProductSponsor::create([
                'package_product_id'         => $packageProductId,
                'sponsor_logo_id'            => $sponsorLogoId,
                'embellishment_position_id'  => $sponsor['embellishment_position_id'] ?? null,
                'brochure_link'              => blank($sponsor['brochure_link'] ?? null) ? null : $sponsor['brochure_link'],
                'override_price'             => filled($sponsor['override_price'] ?? null) ? (float) $sponsor['override_price'] : null,
            ]);
        }
    }

    private function persistEmbellishments(array $productIdToPackageProductId, array $embellishments): void
    {
        $packageProductIds = array_values($productIdToPackageProductId);

        PackageProductEmbellishment::whereIn('package_product_id', $packageProductIds ?: [0])->delete();

        foreach ($embellishments as $embellishment) {
            $productId = (int) ($embellishment['product_id'] ?? 0);
            $packageProductId = $productIdToPackageProductId[$productId] ?? null;
            $embellishmentId = $embellishment['embellishment_id'] ?? null;

            if (! $packageProductId || blank($embellishmentId)) {
                continue;
            }

            PackageProductEmbellishment::create([
                'package_product_id'         => $packageProductId,
                'embellishment_id'           => $embellishmentId,
                'embellishment_position_id'  => $embellishment['embellishment_position_id'] ?? null,
                'override_price'             => filled($embellishment['override_price'] ?? null) ? (float) $embellishment['override_price'] : null,
            ]);
        }
    }
}
