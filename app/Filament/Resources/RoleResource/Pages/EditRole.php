<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Deleting master_admin would lock every admin out of the
            // panel (nothing else grants that Gate::before bypass), so it
            // can be edited (e.g. renamed) but never removed here.
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->name !== 'master_admin'),
        ];
    }
}
