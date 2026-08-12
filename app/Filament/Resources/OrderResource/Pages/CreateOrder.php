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

    public function mount(): void
    {
        // See EditOrder::mount() — the inlined product catalog can be large.
        ini_set('memory_limit', '512M');

        parent::mount();
    }

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

        // See EditOrder::afterSave() — clears the grid's local-storage
        // recovery snapshot for the "create" page slot the moment this
        // order is actually saved.
        $this->dispatch('order-grid-saved');
    }
}
