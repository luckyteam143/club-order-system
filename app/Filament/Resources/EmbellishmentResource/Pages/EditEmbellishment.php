<?php

namespace App\Filament\Resources\EmbellishmentResource\Pages;

use App\Filament\Resources\EmbellishmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmbellishment extends EditRecord
{
    protected static string $resource = EmbellishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
