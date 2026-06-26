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
            $orders = Order::where('club_id', $user->club_id);
            return [
                Stat::make('My Orders', $orders->count()),
                Stat::make('Draft Orders', (clone $orders)->where('status', 'draft')->count()),
                Stat::make('Submitted Orders', (clone $orders)->where('status', 'submitted')->count()),
                Stat::make('Total Spent', '£' . number_format((clone $orders)->where('status', 'completed')->sum('total'), 2)),
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
