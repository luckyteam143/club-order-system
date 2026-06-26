<?php

namespace App\Http\Controllers;

use App\Exports\OrderExport;
use App\Models\Order;
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
}
