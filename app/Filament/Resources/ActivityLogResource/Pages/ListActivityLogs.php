<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Concerns\HasFullWidthContent;
use App\Filament\Resources\ActivityLogResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Spatie\Activitylog\Models\Activity;

class ListActivityLogs extends ListRecords
{
    use HasFullWidthContent;

    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clearByModule')
                ->label('Clear by Module')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->form([
                    Forms\Components\Select::make('log_name')
                        ->label('Module')
                        ->options(ActivityLogResource::MODULE_LABELS)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalDescription('This permanently deletes every log entry for the selected module — not just what\'s currently visible on this page.')
                ->action(function (array $data) {
                    $count = Activity::where('log_name', $data['log_name'])->delete();

                    Notification::make()
                        ->title($count.' log entr'.($count === 1 ? 'y' : 'ies').' deleted')
                        ->success()
                        ->send();
                }),
        ];
    }
}
