<x-mail::message>
# New Note on Order #{{ $note->order_id }}

**{{ $note->user?->name ?? 'System' }}** added a note:

> {{ $note->note }}

<x-mail::button :url="$url">
View Order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
