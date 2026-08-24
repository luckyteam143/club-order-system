<?php

namespace App\Filament\Resources\LogoTypeResource\Pages;

use App\Filament\Resources\LogoTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLogoType extends EditRecord
{
    protected static string $resource = LogoTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
