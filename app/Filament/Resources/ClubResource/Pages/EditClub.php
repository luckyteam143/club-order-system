<?php

namespace App\Filament\Resources\ClubResource\Pages;

use App\Filament\Resources\ClubResource;
use App\Filament\Resources\ClubResource\Concerns\PersistsClubItems;
use App\Imports\ClubItemsImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EditClub extends EditRecord
{
    use PersistsClubItems;

    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportItems')
                ->label('Export Items')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('clubs.items.export', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('importItems')
                ->label('Import Items')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV File')
                        ->disk('local')
                        ->directory('imports/club-items')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: ID, Barcode, Name, Club Price, Online Store Price, Has Club Crest (Yes/No), Crest Number. A row with an ID updates that item\'s prices and crest settings. A row with no ID is matched by Barcode — added if the product isn\'t on the club yet, otherwise its prices / crest settings are updated. Items not listed are left unchanged; nothing is deleted. Use "Export Items" for the template.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    set_time_limit(300);
                    ini_set('memory_limit', '512M');

                    try {
                        $import = new ClubItemsImport($this->record);
                        Excel::import($import, $path);
                    } catch (\Throwable $e) {
                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Storage::disk('local')->delete($data['file']);

                    $body = $import->imported . ' item(s) added / updated.';

                    if ($import->errors) {
                        $body .= ' ' . count($import->errors) . ' row(s) skipped: '
                            . implode(' ', array_slice($import->errors, 0, 10));
                    }

                    Notification::make()
                        ->title('Club items imported')
                        ->body($body)
                        ->{$import->errors ? 'warning' : 'success'}()
                        ->send();

                    // Reload so the "Assigned Items" repeater is rebuilt from
                    // the freshly synced club_product pivot.
                    $this->redirect(ClubResource::getUrl('edit', ['record' => $this->record]));
                }),
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
