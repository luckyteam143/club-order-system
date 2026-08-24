<x-mail::message>
# New Order Submitted

**Order #{{ $order->id }}** from **{{ $order->club?->name ?? 'Unknown Club' }}** has just been submitted.

- **Type:** {{ ucfirst($order->type ?? '') }}
- **Total:** ${{ number_format((float) $order->total, 2) }} CAD
- **Submitted:** {{ $order->submitted_at?->format('M j, Y g:i A') }}
@if ($order->team_po)
- **Team / PO #:** {{ $order->team_po }}
@endif

<x-mail::button :url="$url">
View Order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
