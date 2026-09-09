<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Profile;
use App\Filament\Resources\ClubCatalogResource;
use App\Filament\Resources\ClubPackageResource;
use App\Filament\Resources\ClubTeamResource;
use App\Filament\Resources\MediaResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\SponsorLogoResource;
use App\Filament\Widgets\StatsOverview;
use App\Support\CatalogNavigation;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            // User menu → Profile: display name + a Security section for
            // changing the password (current password required).
            ->profile(Profile::class)
            ->brandName('Macron Club Orders')
            ->colors([
                'primary' => Color::Blue,
            ])
            // Explicit, not discovered — a club user should only ever see
            // their own orders, none of the admin-side catalog/inventory/
            // user-management resources that live in the same directory.
            ->resources([
                OrderResource::class,
                // Read-only list of the items an admin has assigned to this
                // club (with the club's own prices), for reference before
                // placing a Club Items order.
                ClubCatalogResource::class,
                // Read-only view of the packages an admin has built for
                // this club — items, embellishments, sponsor logos. No
                // create / edit / delete.
                ClubPackageResource::class,
                SponsorLogoResource::class,
                ClubTeamResource::class,
                // Club users get a Media library scoped to their own club
                // (see Media::scopeVisibleTo) — upload once, then pick from
                // any image field. Gated by the view_/create_/edit_/
                // delete_media permissions.
                MediaResource::class,
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                StatsOverview::class,
            ])
            // Same "Macron Catalog" sidebar links as the admin panel — each
            // item is gated by the view_macron_catalogs permission, so club
            // users only see them once that permission is assigned to their
            // role.
            ->navigationItems(CatalogNavigation::items())
            // Keep "Macron Catalog" at the bottom of the club sidebar too.
            ->navigationGroups([
                'Orders',
                'Administration',
                'Catalogue',
                'Media',
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
