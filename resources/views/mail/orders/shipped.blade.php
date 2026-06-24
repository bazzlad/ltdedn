@php
    $formatMoney = fn (int $minor, string $cur) => ($cur === 'GBP' ? '£' : $cur.' ').number_format($minor / 100, 2);
@endphp
<x-mail::message>
# Your order is on its way

Good news — order **#{{ $order->id }}** has been shipped.

@if ($order->shipping_carrier || $order->shipping_tracking_number)
**Carrier:** {{ $order->shipping_carrier ?: 'See tracking' }}
**Tracking number:** {{ $order->shipping_tracking_number ?: 'N/A' }}
@endif

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($items as $item)
| {{ $item->product_name }}{{ $item->sku_code_snapshot !== 'STANDARD' ? ' ('.$item->sku_code_snapshot.')' : '' }} | {{ $item->quantity }} | {{ $formatMoney((int) $item->line_total_amount, $currency) }} |
@endforeach
</x-mail::table>

@if ($order->shipping_line1)
**Shipping to:**
{{ $order->shipping_name }}
{{ $order->shipping_line1 }}@if ($order->shipping_line2), {{ $order->shipping_line2 }}@endif
{{ $order->shipping_city }} {{ $order->shipping_postal_code }}
{{ $order->shipping_country }}
@endif

If anything looks wrong, just reply to this email and we'll sort it out.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
