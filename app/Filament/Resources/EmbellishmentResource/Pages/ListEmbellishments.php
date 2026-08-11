<?php

namespace App\Filament\Resources\EmbellishmentResource\Pages;

use App\Filament\Resources\EmbellishmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmbellishments extends ListRecords
{
    protected static string $resource = EmbellishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
