<?php

namespace App\Http\Controllers;

use App\Exports\MediaExport;
use Maatwebsite\Excel\Facades\Excel;

class MediaController extends Controller
{
    public function export()
    {
        $user = auth()->user();

        abort_unless($user?->can('view_media') && ! $user->isClub(), 403);

        return Excel::download(new MediaExport(), 'media-' . now()->format('Y-m-d') . '.xlsx');
    }
}
