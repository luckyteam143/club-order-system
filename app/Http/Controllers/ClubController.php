<?php

namespace App\Http\Controllers;

use App\Exports\ClubItemsExport;
use App\Exports\ClubsExport;
use App\Models\Club;
use Maatwebsite\Excel\Facades\Excel;

class ClubController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->can('manage_clubs'), 403);

        return Excel::download(new ClubsExport(), 'clubs-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * One club's assigned items + prices — the download half of the
     * import/export round-trip on the Edit Club screen.
     */
    public function exportItems(Club $club)
    {
        abort_unless(auth()->user()?->can('manage_clubs'), 403);

        $slug = $club->code ?: $club->id;

        return Excel::download(new ClubItemsExport($club), 'club-' . $slug . '-items-' . now()->format('Y-m-d') . '.xlsx');
    }
}
