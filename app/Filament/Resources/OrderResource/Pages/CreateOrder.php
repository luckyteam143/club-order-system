<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Concerns\PersistsOrderGrid;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use PersistsOrderGrid;

    protected static string $resource = OrderResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Save as Draft');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->gridState = json_decode($data['grid_state'] ?? '{}', true) ?: ['columns' => [], 'rows' => []];
        unset($data['grid_state']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistGridState($this->record, $this->gridState ?? ['columns' => [], 'rows' => []]);
    }
}
