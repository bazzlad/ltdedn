<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PushShopifyFulfilment implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::query()->with('connection')->find($this->orderId);

        if (! $order || ! $order->connection) {
            return;
        }

        $credentials = $order->connection->credentials ?? [];
        $accessToken = $credentials['access_token'] ?? null;
        $storeUrl = rtrim((string) $order->connection->store_url, '/');

        if (! $accessToken || $storeUrl === '') {
            $this->recordFailure($order, 'Missing Shopify access token or store URL.');

            return;
        }

        if (! $this->isAllowedShopifyUrl($storeUrl)) {
            $this->recordFailure($order, 'Invalid Shopify store URL.');

            return;
        }

        $fulfillmentOrderIds = $this->fulfillmentOrderIds($order, $storeUrl, (string) $accessToken);

        if ($fulfillmentOrderIds === null) {
            return;
        }

        if ($fulfillmentOrderIds === []) {
            $this->recordFailure($order, 'Missing Shopify fulfillment order id.');

            return;
        }

        $response = Http::withHeaders($this->shopifyHeaders((string) $accessToken))
            ->post($this->adminUrl($storeUrl, 'fulfillments.json'), [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => array_map(
                        fn (string $fulfillmentOrderId): array => ['fulfillment_order_id' => $fulfillmentOrderId],
                        $fulfillmentOrderIds,
                    ),
                    'notify_customer' => false,
                    'tracking_info' => [
                        'company' => $order->shipping_carrier,
                        'number' => $order->shipping_tracking_number,
                    ],
                ],
            ]);

        if ($response->successful()) {
            $this->recordSuccess($order, ['status' => $response->status()]);

            return;
        }

        $this->recordFailure($order, 'Shopify pushback failed with HTTP '.$response->status().'.');
    }

    /**
     * @return list<string>|null
     */
    private function fulfillmentOrderIds(Order $order, string $storeUrl, string $accessToken): ?array
    {
        $existing = data_get($order->meta, 'shopify_fulfillment_order_ids');

        if (is_array($existing)) {
            return array_values(array_filter(array_map('strval', $existing)));
        }

        $existing = data_get($order->meta, 'shopify_fulfillment_order_id');

        if ($existing) {
            return [(string) $existing];
        }

        if (! $order->external_order_id) {
            return [];
        }

        $response = Http::withHeaders($this->shopifyHeaders($accessToken))
            ->get($this->adminUrl($storeUrl, 'orders/'.$order->external_order_id.'/fulfillment_orders.json'));

        if (! $response->successful()) {
            $this->recordFailure($order, $this->fulfillmentOrderFetchFailure($response));

            return null;
        }

        $ids = collect($response->json('fulfillment_orders', []))
            ->filter(fn (mixed $fulfillmentOrder): bool => is_array($fulfillmentOrder)
                && ! in_array($fulfillmentOrder['status'] ?? null, ['closed', 'cancelled'], true))
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $meta = $order->meta ?? [];
        data_set($meta, 'shopify_fulfillment_order_ids', $ids);
        data_set($meta, 'shopify_fulfillment_order_id', $ids[0]);

        $order->forceFill(['meta' => $meta])->save();

        return $ids;
    }

    private function adminUrl(string $storeUrl, string $path): string
    {
        return $storeUrl.'/admin/api/'.config('services.shopify_connect.api_version', '2025-10').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function shopifyHeaders(string $accessToken): array
    {
        return ['X-Shopify-Access-Token' => $accessToken];
    }

    private function fulfillmentOrderFetchFailure(Response $response): string
    {
        if ($response->status() === 403) {
            return 'Shopify fulfillment order lookup failed with HTTP 403. Reinstall the Shopify app after approving fulfillment-order scopes.';
        }

        return 'Shopify fulfillment order lookup failed with HTTP '.$response->status().'.';
    }

    private function isAllowedShopifyUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && ($host === 'myshopify.com' || str_ends_with($host, '.myshopify.com'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordSuccess(Order $order, array $payload): void
    {
        $order->update([
            'shipment_pushback_status' => 'succeeded',
            'shipment_pushback_error' => null,
            'last_pushback_attempted_at' => now(),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'type' => 'shipment_pushback_success',
            'payload' => $payload + ['platform' => 'shopify'],
        ]);
    }

    private function recordFailure(Order $order, string $error): void
    {
        $order->update([
            'shipment_pushback_status' => 'failed',
            'shipment_pushback_error' => $error,
            'last_pushback_attempted_at' => now(),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'type' => 'shipment_pushback_failure',
            'payload' => ['platform' => 'shopify', 'error' => $error],
        ]);
    }
}
