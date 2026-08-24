<?php

namespace App\Http\Controllers;

use App\Exports\LogoTypeExport;
use Maatwebsite\Excel\Facades\Excel;

class LogoTypeController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->can('manage_logo_types'), 403);

        return Excel::download(new LogoTypeExport(), 'logo-types-' . now()->format('Y-m-d') . '.xlsx');
    }
}
