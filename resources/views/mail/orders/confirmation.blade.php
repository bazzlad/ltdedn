@php
    $formatMoney = fn (int $minor, string $cur) => ($cur === 'GBP' ? '£' : $cur.' ').number_format($minor / 100, 2);
@endphp
<x-mail::message>
# Thanks for your order

We've received your order **#{{ $order->id }}** and payment was successful.

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($items as $item)
| {{ $item->product_name }}{{ $item->sku_code_snapshot !== 'STANDARD' ? ' ('.$item->sku_code_snapshot.')' : '' }} | {{ $item->quantity }} | {{ $formatMoney((int) $item->line_total_amount, $currency) }} |
@endforeach
</x-mail::table>

**Subtotal:** {{ $formatMoney((int) $order->subtotal_amount, $currency) }}
**Shipping:** {{ $formatMoney((int) $order->shipping_amount, $currency) }}
**Tax:** {{ $formatMoney((int) $order->tax_amount, $currency) }}
**Total:** {{ $formatMoney((int) $order->total_amount, $currency) }}

@if ($order->shipping_line1)
**Shipping to:**
{{ $order->shipping_name }}
{{ $order->shipping_line1 }}@if ($order->shipping_line2), {{ $order->shipping_line2 }}@endif
{{ $order->shipping_city }} {{ $order->shipping_postal_code }}
{{ $order->shipping_country }}
@endif

We'll email you again when your order ships.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
