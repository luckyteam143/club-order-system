<?php

namespace App\Filament\Resources\CoSponsorshipResource\Pages;

use App\Filament\Resources\CoSponsorshipResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoSponsorship extends EditRecord
{
    protected static string $resource = CoSponsorshipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
