<?php

namespace App\Filament\Resources\AttributeResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\AttributeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttributes extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
