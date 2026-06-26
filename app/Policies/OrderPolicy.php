<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin() || $user->isSubAdmin()) return true;
        return $user->club_id === $order->club_id;
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isClub() && $user->club_id === $order->club_id) {
            return in_array($order->status, ['draft']);
        }
        return false;
    }
}
