<?php

namespace App\Http\Controllers;

use App\Exports\PackageEmbellishmentsExport;
use App\Exports\PackageItemsExport;
use App\Models\Package;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PackageController extends Controller
{
    /**
     * One package's items + qty + price override — the download half of the
     * import/export round-trip on the Edit Package screen.
     */
    public function exportItems(Package $package)
    {
        abort_unless(auth()->user()?->can('manage_packages'), 403);

        $slug = Str::slug($package->name) ?: $package->id;

        return Excel::download(new PackageItemsExport($package), 'package-'.$slug.'-items-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * One package's embellishment assignments — the download half of the
     * embellishment import/export round-trip on the Edit Package screen.
     */
    public function exportEmbellishments(Package $package)
    {
        abort_unless(auth()->user()?->can('manage_packages'), 403);

        $slug = Str::slug($package->name) ?: $package->id;

        return Excel::download(new PackageEmbellishmentsExport($package), 'package-'.$slug.'-embellishments-'.now()->format('Y-m-d').'.xlsx');
    }
}
