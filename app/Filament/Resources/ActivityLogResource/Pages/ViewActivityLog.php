<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Date / Time')->dateTime('M j, Y g:i A'),
                        TextEntry::make('causer.name')->label('User')->default('System'),
                        TextEntry::make('log_name')
                            ->label('Module')
                            ->formatStateUsing(fn (?string $state) => ActivityLogResource::MODULE_LABELS[$state] ?? ucfirst((string) $state)),
                        TextEntry::make('event')->label('Action')->badge(),
                        TextEntry::make('subject_ref')
                            ->label('Record')
                            ->state(fn ($record) => ActivityLogResource::describeSubject($record))
                            ->placeholder('—'),
                        TextEntry::make('description')->columnSpanFull(),
                    ]),
                Section::make('Changes')
                    ->columns(2)
                    ->visible(fn ($record) => filled($record->attribute_changes?->get('attributes')))
                    ->schema([
                        KeyValueEntry::make('changes_old')->label('Before')->state(fn ($record) => ActivityLogResource::stringifyChanges($record->attribute_changes?->get('old') ?? [])),
                        KeyValueEntry::make('changes_new')->label('After')->state(fn ($record) => ActivityLogResource::stringifyChanges($record->attribute_changes?->get('attributes') ?? [])),
                    ]),
            ]);
    }
}
