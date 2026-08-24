@php
    $record = $getRecord();
    $recordId = $record->getKey();
    $field = $getName();
    $value = (int) $getState();
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
        class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm text-right"
    />
</div>
