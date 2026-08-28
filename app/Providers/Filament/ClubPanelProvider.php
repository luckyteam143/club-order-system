<?php

namespace App\Providers\Filament;

use App\Filament\Resources\ClubTeamResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\SponsorLogoResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClubPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('clubs')
            ->domain('clubs.macronstore.ca')
            ->path('')
            ->login()
            ->brandName('Macron Club Orders')
            ->colors([
                'primary' => Color::Blue,
            ])
            // Explicit, not discovered — a club user should only ever see
            // their own orders, none of the admin-side catalog/inventory/
            // user-management resources that live in the same directory.
            ->resources([
                OrderResource::class,
                SponsorLogoResource::class,
                ClubTeamResource::class,
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                \App\Filament\Widgets\StatsOverview::class,
            ])
            // Same "Macron Catalog" sidebar links as the admin panel — each
            // item is gated by the view_macron_catalogs permission, so club
            // users only see them once that permission is assigned to their
            // role.
            ->navigationItems(\App\Support\CatalogNavigation::items())
            // Keep "Macron Catalog" at the bottom of the club sidebar too.
            ->navigationGroups([
                'Orders',
                'Administration',
                'Catalogue',
                'Macron Catalog',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
