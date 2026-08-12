<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Concerns\PersistsOrderGrid;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    use PersistsOrderGrid;

    protected static string $resource = OrderResource::class;

    public function mount(int | string $record): void
    {
        // The catalog (embedded inline into the grid as JSON) can run into
        // the thousands of products; raise the memory ceiling for this page
        // rather than let a growing catalog fatal it under the shared 128M
        // PHP-FPM limit.
        ini_set('memory_limit', '512M');

        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submitOrder')
                ->label('Submit Order')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This saves your current edits and sends the order in. You won\'t be able to make further changes yourself once it\'s submitted.')
                ->visible(fn () => $this->record->status === 'draft'
                    && (auth()->user()?->isAdmin() || auth()->user()?->club_id === $this->record->club_id))
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->record->update(['status' => 'submitted', 'submitted_at' => now()]);

                    Notification::make()->title('Order submitted')->success()->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(fn () => $this->record->status === 'draft' ? 'Save Draft' : 'Save Changes');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['grid_state'] = json_encode($this->buildGridState($this->record));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->gridState = json_decode($data['grid_state'] ?? '{}', true) ?: ['columns' => [], 'rows' => []];
        unset($data['grid_state']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistGridState($this->record, $this->gridState ?? ['columns' => [], 'rows' => []]);

        // Lets the grid clear its local-storage recovery snapshot the
        // moment a real save succeeds, instead of waiting for the next
        // page load's "does this differ from the server?" comparison.
        $this->dispatch('order-grid-saved');
    }
}
