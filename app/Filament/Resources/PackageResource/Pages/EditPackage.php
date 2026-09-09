<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\PersistsPackageItems;
use App\Imports\PackageEmbellishmentsImport;
use App\Imports\PackageItemsImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EditPackage extends EditRecord
{
    use PersistsPackageItems;

    protected static string $resource = PackageResource::class;

    public function getTitle(): string
    {
        // "(Copy)" flags a just-duplicated package until the first real
        // save, at which point it's a package in its own right.
        return $this->record->is_copy ? "{$this->record->name} (Copy)" : $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Creates a new package with the same products, sponsor logos, and embellishments. Any unsaved edits on this page are not included.')
                ->action(function () {
                    $duplicate = PackageResource::duplicatePackage($this->record);

                    Notification::make()->title('Package duplicated')->success()->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $duplicate]));
                }),
            Actions\Action::make('exportItems')
                ->label('Export Items')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('packages.items.export', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('importItems')
                ->label('Import Items')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV File')
                        ->disk('local')
                        ->directory('imports/package-items')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: ID, Barcode, Name, Qty, Price Override, Has Club Crest (Yes/No), Crest Number, Goalkeeper Item, Player Item, Number Colour. A row with an ID updates that item\'s Qty, Price Override, crest, goalkeeper/player and number-colour settings (its sponsor / embellishment settings are kept). A row with no ID is added as a new item, matched to a product by Barcode — the same product may be added on several rows. Items not listed are left unchanged; nothing is deleted by an import. Use "Export Items" for the template.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    set_time_limit(300);
                    ini_set('memory_limit', '512M');

                    try {
                        $import = new PackageItemsImport($this->record);
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

                    $body = $import->imported.' item row(s) imported.';

                    if ($import->errors) {
                        $body .= ' '.count($import->errors).' issue(s): '
                            .implode(' ', array_slice($import->errors, 0, 10));
                    }

                    Notification::make()
                        ->title('Package items imported')
                        ->body($body)
                        ->{$import->errors ? 'warning' : 'success'}()
                        ->send();

                    // Reload so the Items repeater rebuilds from the pivot.
                    $this->redirect(PackageResource::getUrl('edit', ['record' => $this->record]));
                }),
            Actions\Action::make('exportEmbellishments')
                ->label('Export Embellishments')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('packages.embellishments.export', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('importEmbellishments')
                ->label('Import Embellishments')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV File')
                        ->disk('local')
                        ->directory('imports/package-embellishments')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Columns: ID, Item Barcode, Item Name, Embellishment, Position, Override Price. A row with an ID updates that embellishment row. A row with no ID is added to the package item matched by Item Barcode. Embellishment and Position match by name. Rows not listed are left unchanged; nothing is deleted by an import. Use "Export Embellishments" for the template.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    set_time_limit(300);
                    ini_set('memory_limit', '512M');

                    try {
                        $import = new PackageEmbellishmentsImport($this->record);
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

                    $body = $import->imported.' embellishment row(s) imported.';

                    if ($import->errors) {
                        $body .= ' '.count($import->errors).' issue(s): '
                            .implode(' ', array_slice($import->errors, 0, 10));
                    }

                    Notification::make()
                        ->title('Package embellishments imported')
                        ->body($body)
                        ->{$import->errors ? 'warning' : 'success'}()
                        ->send();

                    // Reload so the Embellishments repeater rebuilds from the pivot.
                    $this->redirect(PackageResource::getUrl('edit', ['record' => $this->record]));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->buildItemsState($this->record);
        $data['sponsors'] = $this->buildSponsorsState($this->record);
        $data['embellishments'] = $this->buildEmbellishmentsState($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->packageItems = $data['items'] ?? [];
        $this->packageSponsors = $data['sponsors'] ?? [];
        $this->packageEmbellishments = $data['embellishments'] ?? [];
        unset($data['items'], $data['sponsors'], $data['embellishments']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistItems(
            $this->record,
            $this->packageItems ?? [],
            $this->packageSponsors ?? [],
            $this->packageEmbellishments ?? [],
        );

        if ($this->record->is_copy) {
            $this->record->update(['is_copy' => false]);
        }
    }
}
