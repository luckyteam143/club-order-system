<?php

namespace App\Filament\Resources\ClubTeamResource\Pages;

use App\Filament\Resources\ClubTeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClubTeam extends EditRecord
{
    protected static string $resource = ClubTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * See CreateClubTeam — same server-side re-enforcement, since the
     * disabled club_id field is UI-only.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isClub()) {
            $data['club_id'] = $this->record->club_id;
        }

        return $data;
    }
}
