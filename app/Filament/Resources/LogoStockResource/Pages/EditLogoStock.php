<?php

namespace App\Filament\Resources\LogoStockResource\Pages;

use App\Filament\Resources\LogoStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLogoStock extends EditRecord
{
    protected static string $resource = LogoStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
