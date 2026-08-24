<?php

namespace App\Filament\Resources\ClubTeamResource\Pages;

use App\Filament\Resources\ClubTeamResource;
use App\Models\ClubTeam;
use Filament\Resources\Pages\CreateRecord;

class CreateClubTeam extends CreateRecord
{
    protected static string $resource = ClubTeamResource::class;

    /**
     * The form's club_id field is already disabled for club users, but
     * that's UI-only — a crafted request could still submit a different
     * club, so it's re-enforced here before the record is created.
     *
     * sort_order isn't a form field at all — it's assigned as the next
     * value after that club's current last team, same as the inline
     * "create new team" flow on the order form. Reordering afterward is
     * done by dragging rows on the list, not by typing a number.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->isClub()) {
            $data['club_id'] = auth()->user()->club_id;
        }

        $data['sort_order'] = (ClubTeam::where('club_id', $data['club_id'])->max('sort_order') ?? 0) + 1;

        return $data;
    }
}
