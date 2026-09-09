<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Concerns\PersistsOrderGrid;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateOrder extends CreateRecord
{
    use PersistsOrderGrid;

    protected static string $resource = OrderResource::class;

    // Filament's default 7xl (80rem) content width plus side padding was
    // squeezing the item-column grid — it can run much wider than that on
    // a package/individual order with several items — into needing
    // horizontal scrolling far sooner than the viewport actually requires.
    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

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

    /**
     * Save / Cancel live in the page header so they're reachable without
     * scrolling past the full order sheet — the default footer form
     * actions are dropped.
     */
    protected function getHeaderActions(): array
    {
        return [
            // No ->keyBindings(['mod+s']) here: Ctrl/Cmd+S is handled inside
            // the order grid (resources/views/filament/forms/order-grid.blade.php),
            // which needs to commit the focused roster cell before saving and
            // to reliably beat the browser's own "Save page" dialog. A key
            // binding here as well would just double-fire the save.
            Actions\Action::make('saveTop')
                ->label('Save as Draft')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action(fn () => $this->create()),
            Actions\Action::make('cancelTop')
                ->label('Cancel')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->url(fn () => $this->previousUrl ?? static::getResource()::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Filament's own CreateRecord default redirects to the resource's
     * 'view' page after a create when one exists — OrderResource has one
     * (ViewOrder, a read-only infolist), so "Save as Draft" was landing
     * there instead of staying on the fully-editable roster grid. A draft
     * still needs more work (roster, items…), so send it to 'edit' instead,
     * same as every other place this app redirects into a fresh order
     * (Duplicate, Submit Order).
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->gridState = json_decode($data['grid_state'] ?? '{}', true) ?: ['columns' => [], 'rows' => []];
        unset($data['grid_state']);

        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistGridState($this->record, $this->gridState ?? ['columns' => [], 'rows' => []]);

        activity('order')
            ->causedBy(auth()->user())
            ->performedOn($this->record)
            ->withChanges(['attributes' => ['status' => $this->record->status], 'old' => []])
            ->log('Order created with status '.(OrderResource::STATUSES[$this->record->status] ?? $this->record->status));

        // See EditOrder::afterSave() — clears the grid's local-storage
        // recovery snapshot for the "create" page slot the moment this
        // order is actually saved.
        $this->dispatch('order-grid-saved');
    }
}
