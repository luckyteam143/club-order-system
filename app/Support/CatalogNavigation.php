<?php

namespace App\Support;

use App\Models\MacronCatalog;
use Filament\Navigation\NavigationItem;

class CatalogNavigation
{
    /**
     * Sidebar links for every Macron catalog flagged "show in main menu".
     *
     * Grouped under a "Macron Catalog" heading and only shown to users who
     * hold the view_macron_catalogs permission. Each link opens in a new tab.
     *
     * Runs while the panel is registering — which also happens during
     * `artisan migrate`, before this table exists — so any failure to read
     * it degrades to "no catalog links" rather than a hard error.
     *
     * @return array<NavigationItem>
     */
    public static function items(): array
    {
        try {
            $catalogs = MacronCatalog::query()
                ->where('show_in_menu', true)
                ->orderBy('sort_order')
                ->orderByDesc('year')
                ->orderBy('name')
                ->get();
        } catch (\Throwable) {
            return [];
        }

        return $catalogs->map(fn (MacronCatalog $catalog) => NavigationItem::make($catalog->name)
            ->group('Macron Catalog')
            ->icon('heroicon-o-book-open')
            ->sort($catalog->sort_order)
            ->visible(fn () => auth()->user()?->can('view_macron_catalogs') ?? false)
            ->url($catalog->link, shouldOpenInNewTab: true))
            ->all();
    }
}
