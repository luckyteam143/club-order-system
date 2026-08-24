<?php

namespace App\Filament\Resources\BrochureResource\Pages;

use App\Filament\Resources\BrochureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBrochure extends EditRecord
{
    protected static string $resource = BrochureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
