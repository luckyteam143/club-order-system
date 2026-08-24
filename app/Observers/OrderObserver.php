<?php

namespace App\Observers;

use App\Models\Order;
use App\Support\OrderNotifier;

class OrderObserver
{
    /**
     * A single hook for every status transition regardless of where it
     * happens (the table's "Submit" action, the Edit form's status select,
     * forecast flows, …), rather than sprinkling notification calls across
     * each of those call sites.
     */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $oldStatus = $order->getOriginal('status');
        $newStatus = $order->status;

        if ($oldStatus === 'draft' && $newStatus === 'submitted') {
            OrderNotifier::submitted($order);

            return;
        }

        OrderNotifier::statusChanged($order, $oldStatus, $newStatus);
    }
}
