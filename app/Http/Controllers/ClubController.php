<?php

namespace App\Http\Controllers;

use App\Exports\ClubsExport;
use Maatwebsite\Excel\Facades\Excel;

class ClubController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->can('manage_clubs'), 403);

        return Excel::download(new ClubsExport(), 'clubs-' . now()->format('Y-m-d') . '.xlsx');
    }
}
