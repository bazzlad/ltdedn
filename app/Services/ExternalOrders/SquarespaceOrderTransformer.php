<?php

namespace App\Services\ExternalOrders;

use App\DTOs\ExternalOrders\NormalizedAddressData;
use App\DTOs\ExternalOrders\NormalizedLineItemData;
use App\DTOs\ExternalOrders\NormalizedOrderData;

class SquarespaceOrderTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function transform(array $payload): NormalizedOrderData
    {
        $orderPayload = is_array($payload['order'] ?? null) ? $payload['order'] : $payload;
        $shipping = is_array($orderPayload['shippingAddress'] ?? null) ? $orderPayload['shippingAddress'] : ($orderPayload['shipping_address'] ?? []);
        $lineItems = collect($orderPayload['lineItems'] ?? $orderPayload['line_items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                $unitAmount = $this->minorAmount($item['unitPricePaid'] ?? $item['unit_price_paid'] ?? $item['unitPrice'] ?? $item['unit_price'] ?? 0);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                return new NormalizedLineItemData(
                    skuCode: (string) ($item['sku'] ?? ''),
                    productTitle: (string) ($item['productName'] ?? $item['product_name'] ?? $item['title'] ?? 'External item'),
                    variantTitle: isset($item['variantName']) ? (string) $item['variantName'] : ($item['variant_name'] ?? null),
                    quantity: $quantity,
                    unitAmount: $unitAmount,
                    lineTotal: $this->minorAmount($item['lineItemSubtotal'] ?? $item['line_item_subtotal'] ?? null) ?: $unitAmount * $quantity,
                    attributes: ['squarespace_line_item_id' => $item['id'] ?? null],
                );
            })
            ->values()
            ->all();

        return new NormalizedOrderData(
            externalOrderId: (string) ($orderPayload['id'] ?? $orderPayload['orderId'] ?? $orderPayload['order_id'] ?? ''),
            externalOrderNumber: isset($orderPayload['orderNumber']) ? (string) $orderPayload['orderNumber'] : ($orderPayload['order_number'] ?? null),
            customerEmail: $orderPayload['customerEmail'] ?? $orderPayload['customer_email'] ?? $orderPayload['email'] ?? null,
            shippingAddress: new NormalizedAddressData(
                name: $shipping['fullName'] ?? $shipping['name'] ?? null,
                phone: $shipping['phone'] ?? null,
                line1: $shipping['address1'] ?? $shipping['line1'] ?? null,
                line2: $shipping['address2'] ?? $shipping['line2'] ?? null,
                city: $shipping['city'] ?? null,
                state: $shipping['state'] ?? $shipping['province'] ?? null,
                postalCode: $shipping['postalCode'] ?? $shipping['postal_code'] ?? $shipping['zip'] ?? null,
                country: $shipping['countryCode'] ?? $shipping['country_code'] ?? $shipping['country'] ?? null,
            ),
            currency: (string) ($orderPayload['currency'] ?? 'GBP'),
            subtotalAmount: $this->minorAmount($orderPayload['subtotal'] ?? $orderPayload['subtotalPrice'] ?? 0),
            shippingAmount: $this->minorAmount($orderPayload['shippingTotal'] ?? $orderPayload['shipping_total'] ?? 0),
            taxAmount: $this->minorAmount($orderPayload['taxTotal'] ?? $orderPayload['tax_total'] ?? 0),
            totalAmount: $this->minorAmount($orderPayload['grandTotal'] ?? $orderPayload['grand_total'] ?? $orderPayload['total'] ?? 0),
            paymentStatus: (string) ($orderPayload['paymentStatus'] ?? $orderPayload['payment_status'] ?? 'unknown'),
            fulfilmentStatus: isset($orderPayload['fulfillmentStatus']) ? (string) $orderPayload['fulfillmentStatus'] : ($orderPayload['fulfillment_status'] ?? null),
            lineItems: $lineItems,
        );
    }

    private function minorAmount(mixed $amount): int
    {
        if (is_array($amount)) {
            $amount = $amount['value'] ?? $amount['amount'] ?? 0;
        }

        return (int) round(((float) $amount) * 100);
    }
}
