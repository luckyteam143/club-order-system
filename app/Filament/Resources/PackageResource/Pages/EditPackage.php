<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\PersistsPackageItems;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
