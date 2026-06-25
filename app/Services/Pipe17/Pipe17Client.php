<?php

namespace App\Services\Pipe17;

use App\Models\Order;
use App\Models\StorefrontConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Pipe17Client
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listShippingRequests(StorefrontConnection $connection, ?string $updatedSince = null, array $statuses = []): array
    {
        $query = array_filter([
            'updatedSince' => $updatedSince,
            'connectionId' => $this->connectionId($connection),
            'locationId' => $this->locationId($connection),
        ], fn ($value) => filled($value));

        foreach ($statuses ?: $this->defaultStatuses() as $status) {
            $query['status'][] = $status;
        }

        $items = [];
        $nextCursor = null;
        $page = 0;

        do {
            $page++;
            $pageQuery = $nextCursor ? array_merge($query, ['cursor' => $nextCursor]) : $query;

            $response = $this->request($connection)->get($this->shippingRequestsPath(), $pageQuery);
            $response->throw();

            $payload = $response->json();
            $pageItems = data_get($payload, 'shippingRequests', data_get($payload, 'data', data_get($payload, 'items', $payload)));

            $items = array_merge($items, is_array($pageItems) ? $pageItems : []);
            $nextCursor = $this->nextCursor(is_array($payload) ? $payload : []);
        } while ($nextCursor && $page < 25);

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->all();
    }

    public function markShippingRequestSent(StorefrontConnection $connection, string $shippingRequestId, int $orderId): void
    {
        $this->request($connection)
            ->put($this->shippingRequestsPath().'/'.rawurlencode($shippingRequestId), [
                'status' => 'sentToFulfillment',
                'extReferenceId' => 'ltdedn-order-'.$orderId,
            ])
            ->throw();
    }

    public function createFulfillment(Order $order): void
    {
        $order->loadMissing(['connection', 'items']);

        if (! $order->connection) {
            throw new \RuntimeException('Missing Pipe17 connection.');
        }

        $this->request($order->connection)
            ->post($this->fulfillmentsPath(), $this->fulfillmentPayload($order))
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    public function fulfillmentPayload(Order $order): array
    {
        $lineItems = $order->items
            ->groupBy(fn ($item) => (string) $item->sku_code_snapshot)
            ->map(fn ($items, string $sku) => [
                'sku' => $sku,
                'quantity' => (int) $items->sum('quantity'),
            ])
            ->values()
            ->all();

        return [
            'shippingRequestId' => (string) $order->external_order_id,
            'extFulfillmentId' => 'ltdedn-order-'.$order->id,
            'status' => 'fulfilled',
            'fulfilledAt' => $order->shipped_at?->toIso8601String() ?? now()->toIso8601String(),
            'tracking' => [
                'carrier' => $order->shipping_carrier,
                'number' => $order->shipping_tracking_number,
            ],
            'lineItems' => $lineItems,
        ];
    }

    private function request(StorefrontConnection $connection): PendingRequest
    {
        $apiKey = data_get($connection->credentials, 'api_key') ?: config('services.pipe17.api_key');

        if (! filled($apiKey)) {
            throw new \RuntimeException('Missing Pipe17 API key.');
        }

        return Http::baseUrl($this->apiUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(2, 250)
            ->withHeaders(['X-Pipe17-Key' => (string) $apiKey]);
    }

    private function apiUrl(): string
    {
        $url = rtrim((string) config('services.pipe17.api_url', 'https://api-v3.pipe17.com/api/v3'), '/');
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowedHosts = collect(config('services.pipe17.allowed_hosts', []))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->values();

        if (($parts['scheme'] ?? null) !== 'https' || $host === '') {
            throw new \RuntimeException('Pipe17 API URL must use HTTPS.');
        }

        if (! $allowedHosts->contains($host) && ! str_ends_with($host, '.pipe17.com')) {
            throw new \RuntimeException('Pipe17 API URL host is not allowed.');
        }

        return $url;
    }

    private function shippingRequestsPath(): string
    {
        return '/shipping-requests';
    }

    private function fulfillmentsPath(): string
    {
        return '/fulfillments';
    }

    private function connectionId(StorefrontConnection $connection): ?string
    {
        return data_get($connection->last_sync_meta, 'pipe17_connection_id') ?: config('services.pipe17.connection_id');
    }

    private function locationId(StorefrontConnection $connection): ?string
    {
        return $connection->external_shop_id ?: config('services.pipe17.location_id');
    }

    /**
     * @return list<string>
     */
    private function defaultStatuses(): array
    {
        return array_values(config('services.pipe17.shipping_request_statuses', ['readyForFulfillment']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nextCursor(array $payload): ?string
    {
        $cursor = data_get($payload, 'nextCursor')
            ?: data_get($payload, 'pageInfo.nextCursor')
            ?: data_get($payload, 'pagination.nextCursor')
            ?: data_get($payload, 'cursor.next');

        return filled($cursor) ? (string) $cursor : null;
    }
}
