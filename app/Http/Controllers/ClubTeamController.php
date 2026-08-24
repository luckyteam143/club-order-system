<?php

namespace App\Http\Controllers;

use App\Exports\ClubTeamExport;
use Maatwebsite\Excel\Facades\Excel;

class ClubTeamController extends Controller
{
    /**
     * Admin/staff only — ClubTeamExport has no per-club scoping (it's a
     * cross-club bulk-edit tool, same as LogoStockController::export()),
     * so a club user hitting this route would download every club's teams.
     */
    public function export()
    {
        abort_unless(auth()->user()?->can('manage_club_teams'), 403);

        return Excel::download(new ClubTeamExport(), 'club-teams-'.now()->format('Y-m-d').'.xlsx');
    }
}
