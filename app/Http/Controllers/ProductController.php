<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function export()
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin(), 403);

        // The full catalog runs into the tens of thousands of rows —
        // raise the ceiling for this one request rather than let it fatal
        // under the shared PHP-FPM pool default (same pattern as the Stock
        // import/Order pages). Measured peak usage against the live
        // catalog (~30k products) is ~470M once PhpSpreadsheet builds the
        // actual worksheet, not just during the query/mapping step — 512M
        // left almost no headroom, so this goes straight to 1G.
        ini_set('memory_limit', '1024M');

        return Excel::download(new ProductsExport(), 'products-' . now()->format('Y-m-d') . '.xlsx');
    }
}
