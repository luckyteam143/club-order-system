<?php

namespace App\Filament\Resources\SponsorLogoResource\Pages;

use App\Filament\Resources\SponsorLogoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSponsorLogos extends ListRecords
{
    protected static string $resource = SponsorLogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
