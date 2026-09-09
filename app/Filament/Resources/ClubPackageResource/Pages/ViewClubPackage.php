<?php

namespace App\Filament\Resources\ClubPackageResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\ClubPackageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewClubPackage extends ViewRecord
{
    use HasFullWidthContent;

    protected static string $resource = ClubPackageResource::class;

    // Read-only — no Edit / Delete header actions.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
