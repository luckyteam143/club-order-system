<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Logs';
    protected static ?string $modelLabel = 'Log';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 5;

    public const MODULE_LABELS = [
        'product'                => 'Product',
        'warehouse'              => 'Warehouse',
        'club'                   => 'Club',
        'package'                => 'Package',
        'stock'                  => 'Stock',
        'user'                   => 'User',
        'role'                   => 'Role',
        'permission'             => 'Permission',
        'attribute'              => 'Attribute',
        'co_sponsorship'         => 'Co-Sponsorship',
        'embellishment'          => 'Embellishment',
        'embellishment_position' => 'Embellishment Position',
        'sponsor_logo'           => 'Sponsor Logo',
        'order'                  => 'Order',
        'logo_stock'             => 'Logo Stock',
        'logo_type'              => 'Logo Type',
        'brochure'               => 'Brochure',
        'club_team'              => 'Club Team',
        'macron_catalog'         => 'Macron Catalog',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date / Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable(),
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Module')
                    ->formatStateUsing(fn (?string $state) => self::MODULE_LABELS[$state] ?? ucfirst((string) $state))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('Record #')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Module')
                    ->options(self::MODULE_LABELS),
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('User')
                    // causer is a polymorphic relation (MorphTo) — Filament's
                    // relationship() filter helper expects a single related
                    // model, so options are built manually instead. Every
                    // causer in this app is a User (system events have none).
                    ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id')),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view'  => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        // Logs are otherwise view-only (no create/edit) — deletion exists
        // purely so accumulated log data can be cleared out in bulk, same
        // gate as viewing them (view_logs).
        return auth()->user()?->can('view_logs') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_logs') ?? false;
    }
}
