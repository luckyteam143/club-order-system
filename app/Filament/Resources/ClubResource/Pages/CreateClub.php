<?php

namespace App\Filament\Resources\ClubResource\Pages;

use App\Filament\Resources\ClubResource;
use App\Filament\Resources\ClubResource\Concerns\PersistsClubItems;
use Filament\Resources\Pages\CreateRecord;

class CreateClub extends CreateRecord
{
    use PersistsClubItems;

    protected static string $resource = ClubResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->clubItems = $data['items'] ?? [];
        unset($data['items']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistItems($this->record, $this->clubItems ?? []);
    }
}
