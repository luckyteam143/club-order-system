@php
    $record = $getRecord();
    $recordId = $record->getKey();
    $field = $getName();
    $value = $getState();
    $displayValue = isset($format) ? $format($value) : $value;
    $seedValue = is_bool($value) ? ($value ? 'true' : 'false') : json_encode($value);
    $type ??= 'text';
@endphp
<div
    x-data="{}"
    x-init="
        let s = Alpine.store('productsEdit');
        if (s.pending[{{ $recordId }}] === undefined) s.pending[{{ $recordId }}] = {};
        if (s.pending[{{ $recordId }}]['{{ $field }}'] === undefined) s.pending[{{ $recordId }}]['{{ $field }}'] = {{ $seedValue }};
    "
>
    <span x-show="!Alpine.store('productsEdit').isEditing({{ $recordId }})" class="fi-ta-text text-sm">
        {{ $displayValue }}
    </span>

    @if ($type === 'select')
        <select
            x-cloak
            x-show="Alpine.store('productsEdit').isEditing({{ $recordId }})"
            x-model="Alpine.store('productsEdit').pending[{{ $recordId }}]['{{ $field }}']"
            data-record-id="{{ $recordId }}"
            data-col="{{ $field }}"
            x-on:keydown="Alpine.store('productsEdit').onKeydown($event)"
            class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm"
        >
            @foreach (($options ?? []) as $optValue => $optLabel)
                <option value="{{ $optValue }}">{{ $optLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'checkbox')
        <input
            type="checkbox"
            x-cloak
            x-show="Alpine.store('productsEdit').isEditing({{ $recordId }})"
            x-model="Alpine.store('productsEdit').pending[{{ $recordId }}]['{{ $field }}']"
            data-record-id="{{ $recordId }}"
            data-col="{{ $field }}"
            x-on:keydown="Alpine.store('productsEdit').onKeydown($event)"
            class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600"
        />
    @else
        <input
            type="{{ $type === 'number' ? 'number' : 'text' }}"
            @if ($type === 'number') step="{{ $step ?? 1 }}" min="0" @endif
            x-cloak
            x-show="Alpine.store('productsEdit').isEditing({{ $recordId }})"
            x-model{{ $type === 'number' ? '.number' : '' }}="Alpine.store('productsEdit').pending[{{ $recordId }}]['{{ $field }}']"
            data-record-id="{{ $recordId }}"
            data-col="{{ $field }}"
            x-on:keydown="Alpine.store('productsEdit').onKeydown($event)"
            class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm"
        />
    @endif
</div>
