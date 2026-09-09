<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Concerns\PersistsOrderGrid;
use App\Filament\Widgets\OrderActivityLog;
use App\Filament\Widgets\OrderNotesWidget;
use App\Support\ForecastWindow;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditOrder extends EditRecord
{
    use PersistsOrderGrid;

    protected static string $resource = OrderResource::class;

    // See CreateOrder::getMaxContentWidth() — same reasoning, so the item
    // grid gets the same extra room on both pages.
    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getFooterWidgets(): array
    {
        return [OrderNotesWidget::class, OrderActivityLog::class];
    }

    public function getTitle(): string
    {
        // Duplicating an order opens the new draft in a page that otherwise
        // looks identical to the one just left — the order # in the
        // heading is the only cue it's a different record. "(Copy)" flags
        // it further until the first real save, at which point it's a
        // normal order in its own right.
        $title = 'Edit Order #'.$this->record->getKey();

        return $this->record->is_copy ? "{$title} (Copy)" : $title;
    }

    public function mount(int | string $record): void
    {
        // The catalog (embedded inline into the grid as JSON) can run into
        // the thousands of products; raise the memory ceiling for this page
        // rather than let a growing catalog fatal it under the shared 128M
        // PHP-FPM limit.
        ini_set('memory_limit', '512M');

        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        $orderKind = $this->record->order_kind;
        $isBulk = in_array($orderKind, ['bulk', 'forecast'], true);
        $canSubmit = $this->record->status === 'draft'
            && (auth()->user()?->isAdmin() || OrderResource::canManageClubOrder($this->record));

        $submitLabel = match ($orderKind) {
            'bulk'     => 'Submit Bulk Order',
            'forecast' => 'Submit Forecast',
            default    => 'Submit Order',
        };

        $actions = [
            // No ->keyBindings(['mod+s']) here: Ctrl/Cmd+S is handled inside
            // the order grid (resources/views/filament/forms/order-grid.blade.php),
            // which needs to commit the focused roster cell before saving and
            // to reliably beat the browser's own "Save page" dialog. A key
            // binding here as well would just double-fire the save.
            Actions\Action::make('saveTop')
                ->label(fn () => $this->record->status === 'draft' ? 'Save Draft' : 'Save Changes')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action(fn () => $this->save()),
            Actions\Action::make('cancelTop')
                ->label('Cancel')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->url(fn () => $this->previousUrl ?? static::getResource()::getUrl('index')),
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('orders.export', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('printPickList')
                ->label('Print Pick List')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('orders.pick-list', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => auth()->user()?->can('manage_picking') ?? false),
            Actions\Action::make('submitOrder')
                ->label($submitLabel)
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This saves your current edits and sends the order in. You won\'t be able to make further changes yourself once it\'s submitted.')
                ->visible($canSubmit)
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->record->update(['status' => 'submitted', 'submitted_at' => now()]);

                    activity('order')
                        ->causedBy(auth()->user())
                        ->performedOn($this->record)
                        ->withChanges(['attributes' => ['status' => 'submitted'], 'old' => ['status' => 'draft']])
                        ->log('Order submitted');

                    Notification::make()->title('Order submitted')->success()->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
            Actions\Action::make('sendForPicking')
                ->label('Send for Picking')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(fn () => OrderResource::canSendForPicking($this->record))
                ->form(fn () => OrderResource::sendForPickingFormSchema($this->record))
                ->action(fn (array $data) => OrderResource::applySendForPicking($this->record, $data)),
        ];

        // A Bulk Order draft additionally gets a Submit <Season> Forecast
        // button for whichever season(s) are currently open for this
        // club — separate from the always-available Submit Bulk Order,
        // and only appearing at all within that window.
        if ($isBulk && $canSubmit && $this->record->club) {
            foreach (ForecastWindow::openSeasons($this->record->club) as $season => $label) {
                $actions[] = Actions\Action::make("submitForecast_{$season}")
                    ->label("Submit {$label}")
                    ->icon('heroicon-o-calendar')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription("This saves your current edits and submits it as your {$label}. You won't be able to make further changes yourself once submitted.")
                    ->action(function () use ($season, $label) {
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                        $this->record->update(['status' => 'forecast_submitted', 'submitted_at' => now(), 'forecast_season' => $season]);

                        activity('order')
                            ->causedBy(auth()->user())
                            ->performedOn($this->record)
                            ->withChanges(['attributes' => ['status' => 'forecast_submitted', 'forecast_season' => $season], 'old' => ['status' => 'draft']])
                            ->log("Order submitted as {$label}");

                        Notification::make()->title("{$label} submitted")->success()->send();

                        $this->redirect(static::getResource()::getUrl('index'));
                    });
            }
        }

        $actions[] = Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Creates a new draft order with the same roster, items, sponsors, and embellishments. Any unsaved edits on this page are not included.')
                ->visible(fn () => in_array($this->record->status, ['draft', 'submitted'])
                    && (auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin() || OrderResource::canManageClubOrder($this->record)))
                ->action(function () {
                    $duplicate = OrderResource::duplicateOrder($this->record);

                    Notification::make()->title('Order duplicated as a new draft')->success()->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $duplicate]));
                });

        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(fn () => $this->record->status === 'draft' ? 'Save Draft' : 'Save Changes');
    }

    /**
     * Save / Cancel live in the page header (see getHeaderActions()) so
     * they're reachable without scrolling past the full order sheet — the
     * default footer form actions are dropped.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['grid_state'] = json_encode($this->buildGridState($this->record));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->gridState = json_decode($data['grid_state'] ?? '{}', true) ?: ['columns' => [], 'rows' => []];
        unset($data['grid_state']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Captured before persistGridState()/is_copy run their own
        // ->update() calls below, each of which would otherwise overwrite
        // getChanges() with just their own diff.
        $formChanges = $this->record->getChanges();
        $totalBefore = (float) $this->record->total;
        $rosterBefore = $this->rosterCellMap($this->record->id);

        $this->persistGridState($this->record, $this->gridState ?? ['columns' => [], 'rows' => []]);

        if ($this->record->is_copy) {
            $this->record->update(['is_copy' => false]);
        }

        $this->logOrderUpdate($formChanges, $totalBefore, $rosterBefore);

        // Lets the grid clear its local-storage recovery snapshot the
        // moment a real save succeeds, instead of waiting for the next
        // page load's "does this differ from the server?" comparison.
        $this->dispatch('order-grid-saved');
    }

    /**
     * size/qty for every roster cell, keyed as "p{row_id}:i{item_id}" — the
     * same row.key/col.key format the grid's Alpine component uses, so a
     * changed key can be handed straight to it for highlighting. Both ids
     * keep their identity across a save as long as they still exist, so
     * comparing two of these maps only surfaces cells whose size/qty
     * actually changed (or that were added/removed), not the
     * delete-and-recreate every save does to the cells themselves.
     *
     * @return array<string, array{size: ?string, qty: int}>
     */
    private function rosterCellMap(int $orderId): array
    {
        return \App\Models\OrderItemCell::query()
            ->join('order_items', 'order_items.id', '=', 'order_item_cells.order_item_id')
            ->where('order_items.order_id', $orderId)
            ->get(['order_item_cells.order_player_row_id', 'order_item_cells.order_item_id', 'order_item_cells.size', 'order_item_cells.qty'])
            ->mapWithKeys(fn ($c) => ["p{$c->order_player_row_id}:i{$c->order_item_id}" => ['size' => $c->size, 'qty' => (int) $c->qty]])
            ->all();
    }

    /**
     * @param  array<string, array{size: ?string, qty: int}>  $before
     * @param  array<string, array{size: ?string, qty: int}>  $after
     * @return array<int, array{key: string, row_id: int, item_id: int, old_size: ?string, new_size: ?string, old_qty: ?int, new_qty: ?int}>
     */
    private function diffRosterCells(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            preg_match('/^p(\d+):i(\d+)$/', $key, $matches);
            $rowId = (int) ($matches[1] ?? 0);
            $itemId = (int) ($matches[2] ?? 0);

            $changes[] = [
                'key'      => $key,
                'row_id'   => $rowId,
                'item_id'  => $itemId,
                'old_size' => $old['size'] ?? null,
                'new_size' => $new['size'] ?? null,
                'old_qty'  => $old['qty'] ?? null,
                'new_qty'  => $new['qty'] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * Renders each changed cell as "Player #No — Item: size changed from X
     * to Y" so the log names exactly which roster row and item column
     * moved, not just that "something" in the roster did.
     */
    private function describeCellChanges(array $cellChanges): string
    {
        $order = $this->record->fresh(['playerRows', 'orderItems.product']);
        $rowsById = $order->playerRows->keyBy('id');
        $itemsById = $order->orderItems->keyBy('id');

        $descriptions = collect($cellChanges)->map(function (array $c) use ($rowsById, $itemsById) {
            $row = $rowsById->get($c['row_id']);
            $item = $itemsById->get($c['item_id']);

            $rowLabel = $row
                ? trim(($row->player_name ?: 'Unnamed player').(filled($row->number) ? " #{$row->number}" : ''))
                : "Row #{$c['row_id']}";
            $itemLabel = $item?->product?->name ?? "Item #{$c['item_id']}";

            $bits = [];

            if ($c['old_size'] === null && $c['new_size'] !== null) {
                $bits[] = "size set to \"{$c['new_size']}\"";
            } elseif ($c['new_size'] === null && $c['old_size'] !== null) {
                $bits[] = "size cleared (was \"{$c['old_size']}\")";
            } elseif ($c['old_size'] !== $c['new_size']) {
                $bits[] = "size changed from \"{$c['old_size']}\" to \"{$c['new_size']}\"";
            }

            if ($c['old_qty'] !== null && $c['new_qty'] !== null && (int) $c['old_qty'] !== (int) $c['new_qty']) {
                $bits[] = "qty {$c['old_qty']} \u{2192} {$c['new_qty']}";
            }

            return "{$rowLabel} — {$itemLabel}: ".implode(', ', $bits);
        });

        $maxShown = 5;
        $shown = $descriptions->take($maxShown)->implode('; ');

        if ($descriptions->count() > $maxShown) {
            $shown .= ' (+'.($descriptions->count() - $maxShown).' more)';
        }

        return $shown;
    }

    /**
     * Every save that changes something gets a log entry, starting with the
     * order's very first draft save — status moves, office-use fields, and
     * roster/size/pricing edits are all worth an audit trail entry. Once an
     * order is no longer a draft, roster/size changes also get the exact
     * row/item and old→new size spelled out, and the touched cells are
     * remembered so the grid can highlight them on next load — irrelevant
     * noise while still freely drafting, worth flagging once submitted.
     */
    private function logOrderUpdate(array $formChanges, float $totalBefore, array $rosterBefore): void
    {
        unset($formChanges['updated_at'], $formChanges['total'], $formChanges['is_copy'], $formChanges['last_changed_cells']);

        $totalAfter = (float) $this->record->total;
        $isDraft = $this->record->status === 'draft';
        $cellChanges = $this->diffRosterCells($rosterBefore, $this->rosterCellMap($this->record->id));

        if (! $isDraft) {
            // Reflects "changed by the most recent save" — clears itself
            // out on the next save if that one didn't touch the roster.
            $this->record->update(['last_changed_cells' => collect($cellChanges)->pluck('key')->values()->all()]);
        }

        $fieldLabels = [
            'status' => 'Status', 'notes' => 'Order Notes', 'team_po' => 'Team/PO #',
            'coach_manager' => 'Coach/Manager', 'shipping_address' => 'Shipping Address',
            'phone' => 'Phone', 'email' => 'Email', 'order_date' => 'Order Date',
            'b2b_number' => 'B2B Number', 'qb_invoice' => 'QB Invoice #', 'brochure_link' => 'Brochure Link',
            'required_by_date' => 'Required By Date',
            'club_id' => 'Club', 'package_id' => 'Package', 'type' => 'Type', 'order_kind' => 'Order Kind', 'forecast_season' => 'Forecast Season',
        ];

        $parts = [];

        if (array_key_exists('status', $formChanges)) {
            $parts[] = 'status changed to '.(OrderResource::STATUSES[$formChanges['status']] ?? $formChanges['status']);
        }

        $otherChanges = collect($formChanges)->except('status');
        if ($otherChanges->isNotEmpty()) {
            $names = $otherChanges->keys()->map(fn ($f) => $fieldLabels[$f] ?? $f)->implode(', ');
            $parts[] = "details updated ({$names})";
        }

        if (! empty($cellChanges)) {
            $parts[] = $isDraft
                ? 'roster/sizes updated'
                : 'roster updated — '.$this->describeCellChanges($cellChanges);
        }

        if (abs($totalAfter - $totalBefore) > 0.001) {
            $parts[] = sprintf('total changed from $%s to $%s', number_format($totalBefore, 2), number_format($totalAfter, 2));
        }

        if (empty($parts)) {
            return;
        }

        activity('order')
            ->causedBy(auth()->user())
            ->performedOn($this->record)
            ->withChanges([
                'attributes' => array_merge($formChanges, ['total' => $totalAfter, 'roster_changes' => $cellChanges]),
                'old'        => array_merge(array_fill_keys(array_keys($formChanges), null), ['total' => $totalBefore]),
            ])
            ->log('Order '.implode('; ', $parts));
    }
}
