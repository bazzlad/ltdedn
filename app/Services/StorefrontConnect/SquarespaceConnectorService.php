<?php

namespace App\Services\StorefrontConnect;

use App\Models\StorefrontConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SquarespaceConnectorService
{
    public function isConfigured(): bool
    {
        return filled(config('services.squarespace_connect.client_id'))
            && filled(config('services.squarespace_connect.client_secret'));
    }

    public function authorizationUrl(string $state, ?string $websiteId = null): string
    {
        $query = [
            'client_id' => config('services.squarespace_connect.client_id'),
            'redirect_uri' => route('connect.squarespace.callback'),
            'scope' => implode(',', config('services.squarespace_connect.scopes', [])),
            'state' => $state,
            'access_type' => 'offline',
        ];

        if ($websiteId) {
            $query['website_id'] = $websiteId;
        }

        return 'https://login.squarespace.com/api/1/login/oauth/provider/authorize?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{access_token: string, refresh_token: string|null, token_expires_at: \Carbon\CarbonImmutable|null}
     *
     * @throws RequestException
     */
    public function exchangeCode(string $code): array
    {
        /** @var array<string, mixed> $payload */
        $payload = Http::asForm()
            ->withBasicAuth((string) config('services.squarespace_connect.client_id'), (string) config('services.squarespace_connect.client_secret'))
            ->withHeaders(['User-Agent' => $this->userAgent()])
            ->post('https://login.squarespace.com/api/1/login/oauth/provider/tokens', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('connect.squarespace.callback'),
            ])
            ->throw()
            ->json();

        return [
            'access_token' => (string) ($payload['access_token'] ?? $payload['token']),
            'refresh_token' => isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            'token_expires_at' => $this->timestampToCarbon($payload['access_token_expires_at'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws RequestException
     */
    public function registerOrderWebhook(StorefrontConnection $connection): ?array
    {
        $accessToken = data_get($connection->credentials, 'access_token');

        if (! $accessToken) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = Http::withToken($accessToken)
            ->withHeaders(['User-Agent' => $this->userAgent()])
            ->post('https://api.squarespace.com/1.0/webhook_subscriptions', [
                'endpointUrl' => route('webhooks.squarespace', $connection),
                'topics' => ['order.create'],
            ])
            ->throw()
            ->json();

        return $payload;
    }

    private function timestampToCarbon(mixed $timestamp): ?CarbonImmutable
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) floor((float) $timestamp));
    }

    private function userAgent(): string
    {
        return (string) config('services.squarespace_connect.user_agent', 'LTD EDN Connect');
    }
}
