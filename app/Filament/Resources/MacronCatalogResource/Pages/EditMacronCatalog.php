<?php

namespace App\Filament\Resources\MacronCatalogResource\Pages;

use App\Filament\Resources\MacronCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMacronCatalog extends EditRecord
{
    protected static string $resource = MacronCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
