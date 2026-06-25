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

class OrderDeskWebhookController extends Controller
{
    public function __invoke(Request $request, StorefrontConnection $connection, ExternalOrderImportService $service): JsonResponse
    {
        abort_unless($this->platformValue($connection) === StorefrontPlatform::OrderDesk->value, 404);
        abort_unless($this->hasValidStoreId($request, $connection), 401);
        abort_unless($this->hasValidSignature($request, $connection), 401);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $orderPayload = $this->orderPayload($payload);
        $normalized = $this->normalize($orderPayload);

        $import = $service->import(
            $connection,
            $normalized,
            $payload,
            $request->header('X-ORDER-DESK-APPOINTMENT-ID') ?: $request->header('X-ORDER-DESK-HASH'),
        );

        return response()->json([
            'status' => $import->status->value,
            'import_id' => $import->id,
            'order_id' => $import->order_id,
        ]);
    }

    private function hasValidStoreId(Request $request, StorefrontConnection $connection): bool
    {
        $storeId = (string) $request->header('X-ORDER-DESK-STORE-ID', '');

        return $storeId !== '' && hash_equals((string) $connection->external_shop_id, $storeId);
    }

    private function hasValidSignature(Request $request, StorefrontConnection $connection): bool
    {
        $signature = (string) $request->header('X-ORDER-DESK-HASH', '');
        $secret = $this->signatureSecret($connection);

        if ($signature === '' || $secret === '') {
            return false;
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $orderValue = $payload['order'] ?? null;
        $candidates = [];

        if (is_string($orderValue)) {
            $candidates[] = $orderValue;
        } elseif (is_array($orderValue)) {
            $candidates[] = json_encode($orderValue, JSON_THROW_ON_ERROR);
            $candidates[] = json_encode($orderValue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            $binary = hash_hmac('sha256', $candidate, $secret, true);
            $hex = hash_hmac('sha256', $candidate, $secret);

            if (hash_equals(base64_encode($binary), $signature) || hash_equals($hex, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function platformValue(StorefrontConnection $connection): string
    {
        return $connection->platform instanceof StorefrontPlatform
            ? $connection->platform->value
            : (string) $connection->platform;
    }

    private function signatureSecret(StorefrontConnection $connection): string
    {
        return (string) ($connection->webhook_secret ?: data_get($connection->credentials, 'api_key', ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function orderPayload(array $payload): array
    {
        $order = $payload['order'] ?? $payload;

        if (is_string($order)) {
            $decoded = json_decode($order, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($order) ? $order : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalize(array $payload): NormalizedOrderData
    {
        $shipping = $this->shippingAddress($payload);
        $lineItems = collect(data_get($payload, 'items', data_get($payload, 'order_items', data_get($payload, 'line_items', []))))
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                $quantity = max(1, (int) data_get($item, 'quantity', 1));
                $unitAmount = $this->minorAmount(
                    data_get($item, 'price', data_get($item, 'unit_price', data_get($item, 'unit_amount', 0)))
                );

                return new NormalizedLineItemData(
                    skuCode: trim((string) data_get($item, 'code', data_get($item, 'sku', data_get($item, 'item_code', '')))),
                    productTitle: (string) data_get($item, 'name', data_get($item, 'title', 'External item')),
                    variantTitle: $this->variantTitle($item),
                    quantity: $quantity,
                    unitAmount: $unitAmount,
                    lineTotal: $this->minorAmount(data_get($item, 'line_total', data_get($item, 'total', null))) ?: $unitAmount * $quantity,
                    attributes: [
                        'orderdesk_item_id' => data_get($item, 'id'),
                        'orderdesk_code' => data_get($item, 'code'),
                    ],
                );
            })
            ->values()
            ->all();

        return new NormalizedOrderData(
            externalOrderId: (string) data_get($payload, 'id', data_get($payload, 'order_id', data_get($payload, 'source_id', ''))),
            externalOrderNumber: $this->nullableString(data_get($payload, 'source_id', data_get($payload, 'order_number', data_get($payload, 'id')))),
            customerEmail: $this->nullableString(data_get($payload, 'email', data_get($payload, 'customer.email', data_get($payload, 'shipping.email')))),
            shippingAddress: new NormalizedAddressData(
                name: $this->shippingName($shipping),
                phone: $this->nullableString(data_get($shipping, 'phone')),
                line1: $this->nullableString(data_get($shipping, 'address1', data_get($shipping, 'line1'))),
                line2: $this->nullableString(data_get($shipping, 'address2', data_get($shipping, 'line2'))),
                city: $this->nullableString(data_get($shipping, 'city')),
                state: $this->nullableString(data_get($shipping, 'state', data_get($shipping, 'province'))),
                postalCode: $this->nullableString(data_get($shipping, 'postal_code', data_get($shipping, 'zip'))),
                country: $this->nullableString(data_get($shipping, 'country', data_get($shipping, 'country_code'))),
            ),
            currency: (string) data_get($payload, 'currency', data_get($payload, 'currency_code', 'GBP')),
            subtotalAmount: $this->minorAmount(data_get($payload, 'subtotal', data_get($payload, 'subtotal_amount', 0))),
            shippingAmount: $this->minorAmount(data_get($payload, 'shipping_total', data_get($payload, 'shipping', 0))),
            taxAmount: $this->minorAmount(data_get($payload, 'tax_total', data_get($payload, 'tax', 0))),
            totalAmount: $this->minorAmount(data_get($payload, 'total', data_get($payload, 'order_total', data_get($payload, 'grand_total', 0)))),
            paymentStatus: (string) data_get($payload, 'payment_status', data_get($payload, 'payment_status_name', 'paid')),
            fulfilmentStatus: $this->nullableString(data_get($payload, 'fulfillment_status', data_get($payload, 'folder_name'))),
            lineItems: $lineItems,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function shippingAddress(array $payload): array
    {
        $shipping = data_get($payload, 'shipping', data_get($payload, 'shipping_address', []));

        return is_array($shipping) ? $shipping : [];
    }

    /**
     * @param  array<string, mixed>  $shipping
     */
    private function shippingName(array $shipping): ?string
    {
        $name = $this->nullableString(data_get($shipping, 'name'));

        if ($name) {
            return $name;
        }

        return trim((string) data_get($shipping, 'first_name', '').' '.(string) data_get($shipping, 'last_name', '')) ?: null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function variantTitle(array $item): ?string
    {
        $variant = data_get($item, 'variation_name', data_get($item, 'variant_title'));

        if (is_string($variant) && $variant !== '') {
            return $variant;
        }

        $variationList = data_get($item, 'variation_list');

        return is_array($variationList) && $variationList !== []
            ? collect($variationList)->map(fn ($value, $key) => "{$key}: {$value}")->implode(', ')
            : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function minorAmount(mixed $amount): int
    {
        if (is_array($amount)) {
            $amount = $amount['value'] ?? $amount['amount'] ?? 0;
        }

        return (int) round(((float) $amount) * 100);
    }
}
