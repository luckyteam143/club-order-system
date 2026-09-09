<?php

namespace App\Filament\Resources\ClubCatalogResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\ClubCatalogResource;
use Filament\Resources\Pages\ListRecords;

class ListClubCatalog extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = ClubCatalogResource::class;

    // View-only catalog — no "New" action.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
