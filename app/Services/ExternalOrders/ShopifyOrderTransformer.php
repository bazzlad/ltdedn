<?php

namespace App\Services\ExternalOrders;

use App\DTOs\ExternalOrders\NormalizedAddressData;
use App\DTOs\ExternalOrders\NormalizedLineItemData;
use App\DTOs\ExternalOrders\NormalizedOrderData;

class ShopifyOrderTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function transform(array $payload): NormalizedOrderData
    {
        $shipping = is_array($payload['shipping_address'] ?? null) ? $payload['shipping_address'] : [];
        $lineItems = collect($payload['line_items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => new NormalizedLineItemData(
                skuCode: (string) ($item['sku'] ?? ''),
                productTitle: (string) ($item['title'] ?? $item['name'] ?? 'External item'),
                variantTitle: isset($item['variant_title']) ? (string) $item['variant_title'] : null,
                quantity: max(1, (int) ($item['quantity'] ?? 1)),
                unitAmount: $this->minorAmount($item['price'] ?? 0),
                lineTotal: $this->minorAmount($item['price'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1)),
                attributes: ['shopify_line_item_id' => $item['id'] ?? null],
            ))
            ->values()
            ->all();

        return new NormalizedOrderData(
            externalOrderId: (string) ($payload['id'] ?? $payload['admin_graphql_api_id'] ?? ''),
            externalOrderNumber: isset($payload['order_number']) ? (string) $payload['order_number'] : ($payload['name'] ?? null),
            customerEmail: $payload['email'] ?? null,
            shippingAddress: new NormalizedAddressData(
                name: trim((string) (($shipping['name'] ?? '') ?: trim(($shipping['first_name'] ?? '').' '.($shipping['last_name'] ?? '')))) ?: null,
                phone: $shipping['phone'] ?? null,
                line1: $shipping['address1'] ?? null,
                line2: $shipping['address2'] ?? null,
                city: $shipping['city'] ?? null,
                state: $shipping['province_code'] ?? $shipping['province'] ?? null,
                postalCode: $shipping['zip'] ?? null,
                country: $shipping['country_code'] ?? $shipping['country'] ?? null,
            ),
            currency: (string) ($payload['currency'] ?? 'GBP'),
            subtotalAmount: $this->minorAmount($payload['subtotal_price'] ?? 0),
            shippingAmount: $this->minorAmount(data_get($payload, 'total_shipping_price_set.shop_money.amount', 0)),
            taxAmount: $this->minorAmount($payload['total_tax'] ?? 0),
            totalAmount: $this->minorAmount($payload['total_price'] ?? 0),
            paymentStatus: (string) ($payload['financial_status'] ?? 'unknown'),
            fulfilmentStatus: isset($payload['fulfillment_status']) ? (string) $payload['fulfillment_status'] : null,
            lineItems: $lineItems,
        );
    }

    private function minorAmount(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
