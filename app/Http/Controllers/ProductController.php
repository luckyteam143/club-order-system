<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin(), 403);

        return Excel::download(new ProductsExport(), 'products-' . now()->format('Y-m-d') . '.xlsx');
    }
}
