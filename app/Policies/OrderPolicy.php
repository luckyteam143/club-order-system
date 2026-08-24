<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin() || $user->isSubAdmin()) return true;

        if ($user->isClubSubUser()) {
            return $user->id === $order->created_by;
        }

        return $user->isClub() && $user->club_id === $order->club_id;
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isAdmin()) return true;

        if ($user->isClubSubUser() && $user->id === $order->created_by) {
            return in_array($order->status, ['draft']);
        }

        if ($user->isMasterClub() && $user->club_id === $order->club_id) {
            return in_array($order->status, ['draft']);
        }

        return false;
    }

    /**
     * A dedicated permission (delete_orders) rather than isAdmin(), so it
     * can be handed to another role from the Roles screen later — only
     * master_admin has it today. Applies regardless of order status, so a
     * submitted order can be deleted just like a draft.
     *
     * Club users additionally get a narrower version of this baked in
     * directly (not via the permission, which is staff-wide): they can
     * delete an order while it's still a draft — once submitted, deleting
     * is staff-only again. A master club account covers its whole club's
     * drafts; a sub-user only the ones it created itself (same scoping as
     * update() above).
     */
    public function delete(User $user, Order $order): bool
    {
        if ($user->can('delete_orders')) {
            return true;
        }

        if ($order->status !== 'draft') {
            return false;
        }

        if ($user->isClubSubUser()) {
            return $user->id === $order->created_by;
        }

        return $user->isMasterClub() && $user->club_id === $order->club_id;
    }
}
