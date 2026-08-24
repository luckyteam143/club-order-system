<?php

namespace App\Filament\Resources\SponsorLogoResource\Pages;

use App\Filament\Resources\SponsorLogoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSponsorLogo extends EditRecord
{
    protected static string $resource = SponsorLogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * See CreateSponsorLogo — same server-side re-enforcement, since the
     * disabled form fields are UI-only.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isClub()) {
            $data['club_id'] = $this->record->club_id;
            $data['price'] = $this->record->price;
        }

        return $data;
    }
}
