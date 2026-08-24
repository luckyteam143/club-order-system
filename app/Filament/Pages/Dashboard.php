<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OrderActivityLog;
use App\Filament\Widgets\OrderNotesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * OrderNotesWidget/OrderActivityLog need to stay under the panel's normal
 * discoverWidgets() so Livewire can resolve them on real interactions (e.g.
 * "Add Note") — see AdminPanelProvider. Disabling discovery to keep them off
 * the Dashboard broke that resolution entirely (every interaction 419'd).
 * Filtering them out here instead keeps discovery intact.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return collect(parent::getWidgets())
            ->reject(fn (string $widget) => in_array($widget, [OrderNotesWidget::class, OrderActivityLog::class], true))
            ->all();
    }
}
