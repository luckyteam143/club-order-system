<x-mail::message>
# Low Stock Alert

**{{ $logoStock->logo_name }}** ({{ $logoStock->barcode }}) for **{{ $logoStock->club?->name ?? 'Unknown Club' }}** has dropped to **{{ $logoStock->qty }}** in stock — at or below the threshold of {{ $threshold }}.

- **Location:** {{ $logoStock->location ?: '—' }}
- **Position:** {{ $logoStock->position ?: '—' }}

<x-mail::button :url="$url">
View Logo Stock
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
