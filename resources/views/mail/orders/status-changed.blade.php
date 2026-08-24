<x-mail::message>
# Order Status Updated

The status of **Order #{{ $order->id }}** has changed:

**{{ $oldLabel }}** → **{{ $newLabel }}**

@if ($order->team_po)
- **Team / PO #:** {{ $order->team_po }}
@endif

<x-mail::button :url="$url">
View Order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
