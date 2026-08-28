<?php

namespace App\Filament\Resources\MacronCatalogResource\Pages;

use App\Filament\Resources\MacronCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMacronCatalogs extends ListRecords
{
    protected static string $resource = MacronCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
