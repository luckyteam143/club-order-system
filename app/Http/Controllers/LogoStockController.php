<?php

namespace App\Http\Controllers;

use App\Exports\LogoStockExport;
use Maatwebsite\Excel\Facades\Excel;

class LogoStockController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->can('manage_logo_stock'), 403);

        return Excel::download(new LogoStockExport(), 'logo-stock-' . now()->format('Y-m-d') . '.xlsx');
    }
}
