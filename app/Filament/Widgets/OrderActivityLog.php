<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class OrderActivityLog extends BaseWidget
{
    protected static ?string $heading = 'Order Log';

    protected int|string|array $columnSpan = 'full';

    public ?Order $record = null;

    public function table(Table $table): Table
    {
        return $table
            // Order doesn't use the LogsActivity trait — its entries are
            // logged manually (see EditOrder::logOrderUpdate()) so the
            // description can read naturally (status moves, roster/size
            // changes, etc.) — so the query is scoped directly rather than
            // via the trait's morphMany relation.
            ->query(fn () => Activity::query()
                ->where('subject_type', Order::class)
                ->where('subject_id', $this->record?->id ?? 0))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date / Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System'),
                Tables\Columns\TextColumn::make('description')
                    ->wrap(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->checkIfRecordIsSelectableUsing(fn () => auth()->user()?->can('view_logs') ?? false)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('view_logs') ?? false),
                ]),
            ]);
    }
}
