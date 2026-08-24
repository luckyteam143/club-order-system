<?php

namespace App\Filament\Resources\SponsorLogoResource\Pages;

use App\Filament\Resources\SponsorLogoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSponsorLogo extends CreateRecord
{
    protected static string $resource = SponsorLogoResource::class;

    /**
     * The form's club_id/price fields are already disabled for club users,
     * but that's UI-only — a crafted request could still submit different
     * values, so both are re-enforced here before the record is created.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->isClub()) {
            $data['club_id'] = auth()->user()->club_id;
            $data['price'] = 0;
        }

        return $data;
    }
}
