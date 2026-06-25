<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\StorefrontConnect\SquarespaceConnectorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class PushSquarespaceFulfilment implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(SquarespaceConnectorService $squarespace): void
    {
        $order = Order::query()->with('connection')->find($this->orderId);

        if (! $order || ! $order->connection) {
            return;
        }

        try {
            $accessToken = $squarespace->accessTokenForRequest($order->connection);
        } catch (Throwable $throwable) {
            $this->recordFailure($order, 'Squarespace token refresh failed: '.$throwable->getMessage());

            return;
        }

        $storeUrl = rtrim((string) $order->connection->store_url, '/');

        if (! $accessToken || $storeUrl === '') {
            $this->recordFailure($order, 'Missing or expired Squarespace access token or store URL.');

            return;
        }

        if (! $this->isAllowedSquarespaceUrl($storeUrl)) {
            $this->recordFailure($order, 'Invalid Squarespace store URL.');

            return;
        }

        $response = Http::withToken((string) $accessToken)
            ->post($storeUrl.'/api/commerce/orders/'.$order->external_order_id.'/fulfillments', [
                'carrierName' => $order->shipping_carrier,
                'trackingNumber' => $order->shipping_tracking_number,
            ]);

        if ($response->successful()) {
            $this->recordSuccess($order, ['status' => $response->status()]);

            return;
        }

        $this->recordFailure($order, 'Squarespace pushback failed with HTTP '.$response->status().'.');
    }

    private function isAllowedSquarespaceUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && ($host === 'squarespace.com' || str_ends_with($host, '.squarespace.com'));
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
            'payload' => $payload + ['platform' => 'squarespace'],
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
            'payload' => ['platform' => 'squarespace', 'error' => $error],
        ]);
    }
}
