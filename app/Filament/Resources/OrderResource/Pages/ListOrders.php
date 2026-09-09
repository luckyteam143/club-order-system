<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    // Full-width main section (shared by every resource's list page) — the
    // default 7xl left the orders table cramped, forcing horizontal scroll
    // far sooner than the viewport needs.
    use HasFullWidthContent;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
