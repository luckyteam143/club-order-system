<x-filament-panels::page>
    {{ $this->infolist }}

    @php
        $record->loadMissing(['orderItems.product', 'orderItems.sponsors.sponsorLogo', 'orderItems.embellishments.embellishment', 'playerRows.itemCells']);
        $items = $record->orderItems;
        $rows = $record->playerRows;
        $isBulk = in_array($record->order_kind, ['bulk', 'forecast'], true);
    @endphp

    @if ($isBulk)
        {{--
            Bulk/Forecast orders have no named players — one item per row,
            quantity broken down by size, same shape as the Add/Edit grid's
            bulk table (not the named-player roster below, which doesn't
            apply here and can only ever show one size per item anyway).
        --}}
        <x-filament::section heading="Items">
            @if ($items->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No items on this order.</p>
            @else
                @php
                    $allCells = $rows->flatMap(fn ($row) => $row->itemCells);
                    $grandTotal = 0;
                    // Smallest-to-largest by Attribute position, not
                    // alphabetically — matches the Add/Edit grid.
                    $sizeOrder = \App\Models\Attribute::orderBy('position')->orderBy('name')->pluck('name')->flip();
                @endphp
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Item</th>
                                <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Quantity by Size</th>
                                <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Notes</th>
                                <th class="p-2 text-right font-medium text-gray-500 dark:text-gray-400">Item Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                @php
                                    $itemCells = $allCells->where('order_item_id', $item->id)->filter(fn ($c) => $c->size && $c->qty > 0);
                                    $itemTotal = (int) $itemCells->sum('qty');
                                    $grandTotal += $itemTotal;
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="p-2 align-top">
                                        {{ $item->product?->name ?? 'Item' }}
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">${{ number_format($item->unit_price, 2) }} ea</span>
                                    </td>
                                    <td class="p-2 align-top">
                                        @if ($itemCells->isEmpty())
                                            <span class="text-xs text-gray-400">—</span>
                                        @else
                                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                                @foreach ($itemCells->sort(fn ($a, $b) => [$sizeOrder[$a->size] ?? PHP_INT_MAX, $a->size] <=> [$sizeOrder[$b->size] ?? PHP_INT_MAX, $b->size]) as $cell)
                                                    <span><span class="font-medium">{{ $cell->size }}:</span> {{ $cell->qty }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="p-2 align-top">{{ $item->notes ?: '—' }}</td>
                                    <td class="p-2 text-right align-top tabular-nums">{{ $itemTotal }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                <td class="p-2 font-semibold" colspan="3">Grand Total</td>
                                <td class="p-2 text-right font-semibold tabular-nums">{{ $grandTotal }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @else
        <x-filament::section heading="Roster">
            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No roster rows on this order.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Player</th>
                                <th class="p-2 font-medium text-gray-500 dark:text-gray-400">#</th>
                                <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Initials</th>
                                @foreach ($items as $item)
                                    <th class="p-2 font-medium text-gray-500 dark:text-gray-400">
                                        {{ $item->product?->name ?? 'Item' }}
                                        <span class="block font-normal text-gray-400 dark:text-gray-500">${{ number_format($item->unit_price, 2) }} ea</span>
                                    </th>
                                @endforeach
                                <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="p-2">{{ $row->player_name }}</td>
                                    <td class="p-2">{{ $row->number }}</td>
                                    <td class="p-2">{{ $row->initials }}</td>
                                    @foreach ($items as $item)
                                        @php $cell = $row->itemCells->firstWhere('order_item_id', $item->id); @endphp
                                        <td class="p-2">{{ $cell?->size ? "{$cell->size} (x{$cell->qty})" : '—' }}</td>
                                    @endforeach
                                    <td class="p-2">{{ $row->notes ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    @php
        $sponsorLines = $items->flatMap(fn ($item) => $item->sponsors->map(fn ($s) => [
            'item' => $item->product?->name ?? 'Item',
            'label' => $s->sponsorLogo?->name ?? 'Sponsor Logo',
            'price' => (float) $s->price,
        ]));
        $embellishmentLines = $items->flatMap(fn ($item) => $item->embellishments->map(fn ($e) => [
            'item' => $item->product?->name ?? 'Item',
            'label' => $e->embellishment?->name ?? 'Embellishment',
            'price' => (float) $e->price,
        ]));
    @endphp

    @if ($sponsorLines->isNotEmpty() || $embellishmentLines->isNotEmpty())
        <x-filament::section heading="Sponsors & Embellishments">
            <ul class="space-y-1 text-sm">
                @foreach ($sponsorLines as $line)
                    <li>{{ $line['item'] }} — {{ $line['label'] }}: <span class="tabular-nums">${{ number_format($line['price'], 2) }}</span></li>
                @endforeach
                @foreach ($embellishmentLines as $line)
                    <li>{{ $line['item'] }} — {{ $line['label'] }}: <span class="tabular-nums">${{ number_format($line['price'], 2) }}</span></li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
