<?php

namespace App\Filament\Resources\CoSponsorshipResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\CoSponsorshipResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCoSponsorships extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = CoSponsorshipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
