@php
    $formatMoney = fn (int $minor, string $cur) => ($cur === 'GBP' ? '£' : $cur.' ').number_format($minor / 100, 2);
@endphp
<x-mail::message>
# New order received

Order **#{{ $order->id }}** — {{ $formatMoney((int) $order->total_amount, $currency) }}

Buyer: {{ $order->customer_email ?? '(no email)' }}

<x-mail::table>
| Item | SKU | Qty | Line total |
|:-----|:----|:---:|-----------:|
@foreach ($items as $item)
| {{ $item->product_name }} | {{ $item->sku_code_snapshot }} | {{ $item->quantity }} | {{ $formatMoney((int) $item->line_total_amount, $currency) }} |
@endforeach
</x-mail::table>

**Subtotal:** {{ $formatMoney((int) $order->subtotal_amount, $currency) }}
**Shipping:** {{ $formatMoney((int) $order->shipping_amount, $currency) }}
**Tax:** {{ $formatMoney((int) $order->tax_amount, $currency) }}
**Total:** {{ $formatMoney((int) $order->total_amount, $currency) }}

@if ($order->shipping_line1)
**Ship to:**
{{ $order->shipping_name }}
{{ $order->shipping_line1 }}@if ($order->shipping_line2), {{ $order->shipping_line2 }}@endif
{{ $order->shipping_city }} {{ $order->shipping_postal_code }}
{{ $order->shipping_country }}
@endif

<x-mail::button :url="route('admin.sales.show', $order)">View in admin</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
