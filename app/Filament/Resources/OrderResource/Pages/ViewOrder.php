<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Widgets\OrderActivityLog;
use App\Filament\Widgets\OrderNotesWidget;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.resources.order-resource.pages.view-order';

    public function getTitle(): string
    {
        return 'Order #'.$this->record->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('orders.export', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [OrderNotesWidget::class, OrderActivityLog::class];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $isStaff = auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();

        return $infolist
            ->schema([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('club.name')->label('Club'),
                        TextEntry::make('type')->label('Type')->formatStateUsing(fn (?string $state) => ucfirst($state ?? '')),
                        TextEntry::make('package.name')->label('Package')->visible(fn ($record) => filled($record->package_id)),
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (?string $state) => OrderResource::STATUSES[$state] ?? $state)
                            ->badge()
                            ->color(fn (?string $state) => OrderResource::STATUS_COLORS[$state] ?? 'gray'),
                        TextEntry::make('total')->label('Total')->money('CAD'),
                        TextEntry::make('submitted_at')->label('Submitted')->dateTime('M j, Y g:i A')->placeholder('—'),
                        TextEntry::make('clubTeam.team_name')->label('Team')->placeholder('—'),
                        TextEntry::make('team_po')->label('Team / PO #')->placeholder('—'),
                        TextEntry::make('coach_manager')->label('Coach / Manager')->placeholder('—'),
                        TextEntry::make('shipping_address')->label('Shipping Address')->placeholder('—'),
                        TextEntry::make('phone')->label('Phone')->placeholder('—'),
                        TextEntry::make('email')->label('Email')->placeholder('—'),
                        TextEntry::make('notes')->label('Order Notes')->placeholder('—')->columnSpanFull(),
                    ]),
                // Office-use fields — same audience as the editable form's
                // "For Office Use" section (admin/sub-admin only), never
                // shown to club users.
                Section::make('For Office Use')
                    ->columns(3)
                    ->visible($isStaff)
                    ->schema([
                        TextEntry::make('order_date')->label('Order Date')->date()->placeholder('—'),
                        TextEntry::make('b2b_number')->label('B2B Number')->placeholder('—'),
                        TextEntry::make('qb_invoice')->label('QB Invoice #')->placeholder('—'),
                        TextEntry::make('brochure_link')->label('Brochure Link')->placeholder('—')->url(fn ($state) => $state),
                    ]),
            ]);
    }
}
