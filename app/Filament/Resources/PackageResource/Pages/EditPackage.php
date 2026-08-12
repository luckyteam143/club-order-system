<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\PersistsPackageItems;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPackage extends EditRecord
{
    use PersistsPackageItems;

    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
    }
}
