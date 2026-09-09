<?php

namespace App\Filament\Resources\ClubPackageResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\ClubPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListClubPackages extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = ClubPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
