@php
    $record = $getRecord();
    $recordId = $record->getKey();
    $field = $getName();
    $value = (int) $getState();

    // The on-hold reserve for this same product + warehouse, shown read-only
    // next to the editable available-qty input. warehouseStocks is eager
    // loaded by StockResource::table().
    $warehouseId = (int) str_replace('warehouse_', '', $field);
    $onHold = (int) ($record->warehouseStocks->firstWhere('warehouse_id', $warehouseId)?->qty_on_hold ?? 0);
@endphp
<div
    x-data="{}"
    x-init="
        let s = Alpine.store('stockEdit');
        let key = '{{ $recordId }}:{{ $field }}';
        if (s.pending[{{ $recordId }}] === undefined) s.pending[{{ $recordId }}] = {};
        if (s.pending[{{ $recordId }}]['{{ $field }}'] === undefined) s.pending[{{ $recordId }}]['{{ $field }}'] = {{ $value }};
        if (s.original[key] === undefined) s.original[key] = {{ $value }};
    "
    class="flex items-center justify-end gap-2"
>
    <input
        type="number"
        min="0"
        step="1"
        data-record-id="{{ $recordId }}"
        data-col="{{ $field }}"
        x-model.number="Alpine.store('stockEdit').pending[{{ $recordId }}]['{{ $field }}']"
        x-on:input="Alpine.store('stockEdit').touch({{ $recordId }}, '{{ $field }}')"
        x-on:keydown="Alpine.store('stockEdit').onKeydown($event)"
        class="fi-input w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm text-right"
    />
    {{-- Inline style, not text-{color} utilities: this app ships no Tailwind
         build for Filament views, so colour utilities silently no-op — see
         the Stock Scanner page for the same workaround. --}}
    <span
        class="ml-1 whitespace-nowrap text-xs font-semibold tabular-nums"
        style="color: {{ $onHold > 0 ? 'rgb(var(--warning-600))' : 'rgb(var(--gray-400))' }}"
        title="On hold at this warehouse"
    >hold {{ $onHold }}</span>
</div>
