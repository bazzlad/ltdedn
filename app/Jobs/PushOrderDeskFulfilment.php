<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class PushOrderDeskFulfilment implements ShouldQueue
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
        $apiKey = $credentials['api_key'] ?? null;
        $storeId = $order->connection->external_shop_id;
        $apiUrl = rtrim((string) config('services.orderdesk.api_url', 'https://app.orderdesk.me/api/v2'), '/');

        if (! $apiKey || ! $storeId) {
            $this->recordFailure($order, 'Missing Order Desk API key or store ID.');

            return;
        }

        $response = Http::withHeaders([
            'ORDERDESK-STORE-ID' => (string) $storeId,
            'ORDERDESK-API-KEY' => (string) $apiKey,
            'Content-Type' => 'application/json',
        ])->post($apiUrl.'/orders/'.rawurlencode((string) $order->external_order_id).'/shipments', [
            'tracking_number' => $order->shipping_tracking_number,
            'carrier_code' => $order->shipping_carrier,
        ]);

        if ($response->successful() && $response->json('status') !== 'error') {
            $this->recordSuccess($order, ['status' => $response->status()]);

            return;
        }

        $message = $response->json('message') ?: $response->json('error');

        $this->recordFailure(
            $order,
            $message
                ? 'Order Desk pushback failed: '.$message
                : 'Order Desk pushback failed with HTTP '.$response->status().'.',
        );
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
            'payload' => $payload + ['platform' => 'orderdesk'],
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
            'payload' => ['platform' => 'orderdesk', 'error' => $error],
        ]);
    }
}
