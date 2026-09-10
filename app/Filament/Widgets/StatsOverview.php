<?php

namespace App\Filament\Widgets;

use App\Models\Club;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();

        if ($user?->isClub()) {
            // Cached per club user for a short window — the dashboard
            // reloads these on every visit and a ~30s-stale count is fine.
            $counts = Cache::remember(
                "dash_stats:club:{$user->id}",
                now()->addSeconds(30),
                function () use ($user) {
                    $orders = $user->isClubSubUser()
                        ? Order::where('created_by', $user->id)
                        : Order::where('club_id', $user->club_id);

                    return [
                        'total' => (clone $orders)->count(),
                        'draft' => (clone $orders)->where('status', 'draft')->count(),
                        'submitted' => (clone $orders)->where('status', 'submitted')->count(),
                        'completed' => (clone $orders)->where('status', 'completed')->count(),
                    ];
                },
            );

            return [
                Stat::make('Total Orders', $counts['total']),
                Stat::make('Draft', $counts['draft'])->color('gray'),
                Stat::make('Submitted', $counts['submitted'])->color('primary'),
                Stat::make('Completed', $counts['completed'])->color('success'),
            ];
        }

        $counts = Cache::remember('dash_stats:admin', now()->addSeconds(30), fn () => [
            'clubs' => Club::count(),
            'orders' => Order::count(),
            'pending' => Order::where('status', 'submitted')->count(),
            'products' => Product::count(),
        ]);

        return [
            Stat::make('Total Clubs', $counts['clubs']),
            Stat::make('Total Orders', $counts['orders']),
            Stat::make('Pending Review', $counts['pending'])->color('warning'),
            Stat::make('Total Products', $counts['products']),
        ];
    }
}
