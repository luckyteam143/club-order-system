<?php

namespace App\Filament\Resources\ClubResource\Pages;

use App\Filament\Resources\ClubResource;
use App\Filament\Resources\ClubResource\Concerns\PersistsClubItems;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClub extends EditRecord
{
    use PersistsClubItems;

    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->buildItemsState($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->clubItems = $data['items'] ?? [];
        unset($data['items']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistItems($this->record, $this->clubItems ?? []);
    }
}
