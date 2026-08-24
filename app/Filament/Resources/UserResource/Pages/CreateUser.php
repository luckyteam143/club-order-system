<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $roleName = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roleName = $data['role_name'] ?? null;
        unset($data['role_name']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->roleName) {
            $this->record->syncRoles([$this->roleName]);
        }
    }
}
