<?php

namespace App\Filament\Resources\EmbellishmentPositionResource\Pages;

use App\Filament\Resources\EmbellishmentPositionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmbellishmentPosition extends EditRecord
{
    protected static string $resource = EmbellishmentPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
