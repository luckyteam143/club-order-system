<?php

namespace App\Http\Controllers;

use App\Exports\StockExport;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin(), 403);

        return Excel::download(new StockExport(), 'stock-' . now()->format('Y-m-d') . '.xlsx');
    }
}
