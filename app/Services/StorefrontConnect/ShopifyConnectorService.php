<?php

namespace App\Services\StorefrontConnect;

use App\Models\StorefrontConnection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ShopifyConnectorService
{
    public function isConfigured(): bool
    {
        return filled(config('services.shopify_connect.client_id'))
            && filled(config('services.shopify_connect.client_secret'));
    }

    public function authorizationUrl(string $shop, string $state): string
    {
        $shopDomain = $this->normalizeShopDomain($shop);

        return 'https://'.$shopDomain.'/admin/oauth/authorize?'.http_build_query([
            'client_id' => config('services.shopify_connect.client_id'),
            'scope' => implode(',', config('services.shopify_connect.scopes', [])),
            'redirect_uri' => route('connect.shopify.callback'),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function verifyCallback(Request $request): bool
    {
        $hmac = (string) $request->query('hmac', '');

        if ($hmac === '' || ! $this->isConfigured()) {
            return false;
        }

        $parameters = $request->query();
        unset($parameters['hmac'], $parameters['signature']);
        ksort($parameters);

        $message = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $expected = hash_hmac('sha256', $message, (string) config('services.shopify_connect.client_secret'));

        return hash_equals($expected, $hmac);
    }

    /**
     * @return array{access_token: string, scope: string|null}
     *
     * @throws RequestException
     */
    public function exchangeCode(string $shop, string $code): array
    {
        $shopDomain = $this->normalizeShopDomain($shop);

        /** @var array{access_token: string, scope?: string|null} $payload */
        $payload = Http::asForm()
            ->post('https://'.$shopDomain.'/admin/oauth/access_token', [
                'client_id' => config('services.shopify_connect.client_id'),
                'client_secret' => config('services.shopify_connect.client_secret'),
                'code' => $code,
            ])
            ->throw()
            ->json();

        return [
            'access_token' => $payload['access_token'],
            'scope' => $payload['scope'] ?? null,
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
        $shopDomain = $connection->external_shop_domain ?: $this->normalizeShopDomain((string) $connection->store_url);

        if (! $accessToken || ! $shopDomain) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])
            ->post('https://'.$shopDomain.'/admin/api/'.config('services.shopify_connect.api_version').'/webhooks.json', [
                'webhook' => [
                    'topic' => 'orders/create',
                    'address' => route('webhooks.shopify', $connection),
                    'format' => 'json',
                ],
            ])
            ->throw()
            ->json();

        return $payload;
    }

    public function normalizeShopDomain(string $shop): string
    {
        $shop = trim(Str::lower($shop));
        $host = parse_url(Str::startsWith($shop, ['http://', 'https://']) ? $shop : 'https://'.$shop, PHP_URL_HOST);
        $host = trim((string) $host, '.');

        if ($host === '' || ! str_ends_with($host, '.myshopify.com') || substr_count($host, '.') < 2) {
            throw new InvalidArgumentException('Enter a valid .myshopify.com store domain.');
        }

        return $host;
    }
}
