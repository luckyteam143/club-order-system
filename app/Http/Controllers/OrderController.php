<?php

namespace App\Http\Controllers;

use App\Exports\OrderExport;
use App\Exports\PickListExport;
use App\Models\Order;
use Illuminate\Auth\Access\AuthorizationException;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
{
    public function export(Order $order)
    {
        $this->authorize('view', $order);

        $filename = 'order-' . $order->id . '-' . $order->club->name . '.xlsx';
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $filename);

        return Excel::download(new OrderExport($order), $filename);
    }

    public function pickList(Order $order)
    {
        $this->authorize('view', $order);

        // Internal-only — never available to a club user, regardless of
        // whether they can otherwise view the order.
        if (! auth()->user()?->can('manage_picking')) {
            throw new AuthorizationException;
        }

        $filename = 'pick-list-' . $order->id . '-' . $order->club->name . '.xlsx';
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $filename);

        return Excel::download(new PickListExport($order), $filename);
    }
}
