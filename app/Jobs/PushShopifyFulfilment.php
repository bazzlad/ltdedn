<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        $fulfillmentOrderId = data_get($order->meta, 'shopify_fulfillment_order_id');

        if (! $accessToken || $storeUrl === '') {
            $this->recordFailure($order, 'Missing Shopify access token or store URL.');

            return;
        }

        if (! $this->isAllowedShopifyUrl($storeUrl)) {
            $this->recordFailure($order, 'Invalid Shopify store URL.');

            return;
        }

        if (! $fulfillmentOrderId) {
            $this->recordFailure($order, 'Missing Shopify fulfillment order id.');

            return;
        }

        $response = Http::withToken((string) $accessToken)
            ->post($storeUrl.'/admin/api/2025-10/fulfillments.json', [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => [
                        ['fulfillment_order_id' => $fulfillmentOrderId],
                    ],
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
