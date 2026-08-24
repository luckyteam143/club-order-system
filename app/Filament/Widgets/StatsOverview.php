<?php

namespace App\Filament\Widgets;

use App\Models\Club;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();

        if ($user?->isClub()) {
            $orders = $user->isClubSubUser()
                ? Order::where('created_by', $user->id)
                : Order::where('club_id', $user->club_id);

            return [
                Stat::make('Total Orders', $orders->count()),
                Stat::make('Draft', (clone $orders)->where('status', 'draft')->count())
                    ->color('gray'),
                Stat::make('Submitted', (clone $orders)->where('status', 'submitted')->count())
                    ->color('primary'),
                Stat::make('Completed', (clone $orders)->where('status', 'completed')->count())
                    ->color('success'),
            ];
        }

        return [
            Stat::make('Total Clubs', Club::count()),
            Stat::make('Total Orders', Order::count()),
            Stat::make('Pending Review', Order::where('status', 'submitted')->count())
                ->color('warning'),
            Stat::make('Total Products', Product::count()),
        ];
    }
}
