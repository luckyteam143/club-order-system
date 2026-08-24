<?php

namespace App\Http\Controllers;

use App\Exports\BrochuresExport;
use Maatwebsite\Excel\Facades\Excel;

class BrochureController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->can('manage_brochures'), 403);

        return Excel::download(new BrochuresExport(), 'brochures-' . now()->format('Y-m-d') . '.xlsx');
    }
}
