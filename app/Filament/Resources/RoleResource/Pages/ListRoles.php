<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
