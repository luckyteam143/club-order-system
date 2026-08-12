<?php

namespace App\Filament\Resources\PackageResource\Pages;

use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PackageResource\Concerns\PersistsPackageItems;
use Filament\Resources\Pages\CreateRecord;

class CreatePackage extends CreateRecord
{
    use PersistsPackageItems;

    protected static string $resource = PackageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->packageItems = $data['items'] ?? [];
        $this->packageSponsors = $data['sponsors'] ?? [];
        $this->packageEmbellishments = $data['embellishments'] ?? [];
        unset($data['items'], $data['sponsors'], $data['embellishments']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistItems(
            $this->record,
            $this->packageItems ?? [],
            $this->packageSponsors ?? [],
            $this->packageEmbellishments ?? [],
        );
    }
}
