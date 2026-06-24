<?php

namespace App\Http\Controllers\Webhooks;

use App\DTOs\ExternalOrders\NormalizedAddressData;
use App\DTOs\ExternalOrders\NormalizedLineItemData;
use App\DTOs\ExternalOrders\NormalizedOrderData;
use App\Enums\StorefrontPlatform;
use App\Http\Controllers\Controller;
use App\Models\StorefrontConnection;
use App\Services\ExternalOrderImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SquarespaceWebhookController extends Controller
{
    public function __invoke(Request $request, StorefrontConnection $connection, ExternalOrderImportService $service): JsonResponse
    {
        abort_unless($this->platformValue($connection) === StorefrontPlatform::Squarespace->value, 404);
        abort_unless($this->hasValidSignature($request, $connection), 401);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $orderPayload = is_array($payload['order'] ?? null) ? $payload['order'] : $payload;
        $normalized = $this->normalize($orderPayload);

        $import = $service->import(
            $connection,
            $normalized,
            $payload,
            $request->header('X-Squarespace-Webhook-Id') ?: $request->header('X-Squarespace-Event-Id'),
        );

        return response()->json([
            'status' => $import->status->value,
            'import_id' => $import->id,
            'order_id' => $import->order_id,
        ]);
    }

    private function hasValidSignature(Request $request, StorefrontConnection $connection): bool
    {
        $signature = (string) (
            $request->header('X-Squarespace-Signature')
            ?: $request->header('X-Squarespace-Hmac-Sha256')
            ?: ''
        );

        $binary = hash_hmac('sha256', $request->getContent(), $connection->webhook_secret, true);
        $base64 = base64_encode($binary);
        $hex = hash_hmac('sha256', $request->getContent(), $connection->webhook_secret);

        return $signature !== '' && (hash_equals($base64, $signature) || hash_equals($hex, $signature));
    }

    private function platformValue(StorefrontConnection $connection): string
    {
        return $connection->platform instanceof StorefrontPlatform
            ? $connection->platform->value
            : (string) $connection->platform;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalize(array $payload): NormalizedOrderData
    {
        $shipping = is_array($payload['shippingAddress'] ?? null) ? $payload['shippingAddress'] : ($payload['shipping_address'] ?? []);
        $lineItems = collect($payload['lineItems'] ?? $payload['line_items'] ?? [])
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
            externalOrderId: (string) ($payload['id'] ?? $payload['orderId'] ?? $payload['order_id'] ?? ''),
            externalOrderNumber: isset($payload['orderNumber']) ? (string) $payload['orderNumber'] : ($payload['order_number'] ?? null),
            customerEmail: $payload['customerEmail'] ?? $payload['customer_email'] ?? $payload['email'] ?? null,
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
            currency: (string) ($payload['currency'] ?? 'GBP'),
            subtotalAmount: $this->minorAmount($payload['subtotal'] ?? $payload['subtotalPrice'] ?? 0),
            shippingAmount: $this->minorAmount($payload['shippingTotal'] ?? $payload['shipping_total'] ?? 0),
            taxAmount: $this->minorAmount($payload['taxTotal'] ?? $payload['tax_total'] ?? 0),
            totalAmount: $this->minorAmount($payload['grandTotal'] ?? $payload['grand_total'] ?? $payload['total'] ?? 0),
            paymentStatus: (string) ($payload['paymentStatus'] ?? $payload['payment_status'] ?? 'unknown'),
            fulfilmentStatus: isset($payload['fulfillmentStatus']) ? (string) $payload['fulfillmentStatus'] : ($payload['fulfillment_status'] ?? null),
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
