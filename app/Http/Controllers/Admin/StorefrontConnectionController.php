<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StorefrontConnectionStatus;
use App\Enums\StorefrontPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStorefrontConnectionRequest;
use App\Models\Artist;
use App\Models\Order;
use App\Models\StorefrontConnection;
use App\Services\StorefrontConnect\StorefrontConnectionStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontConnectionController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', Order::class);

        $connections = StorefrontConnection::query()
            ->with('artist:id,name')
            ->withCount(['orders', 'imports'])
            ->orderBy('platform')
            ->orderBy('name')
            ->get()
            ->map(fn (StorefrontConnection $connection) => $this->connectionRow($connection))
            ->values();

        return Inertia::render('Admin/StorefrontConnections/Index', [
            'connections' => $connections,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('viewAny', Order::class);

        return Inertia::render('Admin/StorefrontConnections/Create', [
            'artists' => Artist::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'platforms' => collect(StorefrontPlatform::cases())
                ->reject(fn (StorefrontPlatform $platform) => in_array($platform, [
                    StorefrontPlatform::LegacyOrderDesk,
                    StorefrontPlatform::Pipe17,
                ], true))
                ->map(fn (StorefrontPlatform $platform) => [
                    'value' => $platform->value,
                    'label' => Str::headline($platform->value),
                ])
                ->values(),
            'statuses' => collect(StorefrontConnectionStatus::cases())
                ->map(fn (StorefrontConnectionStatus $status) => [
                    'value' => $status->value,
                    'label' => Str::headline($status->value),
                ])
                ->values(),
        ]);
    }

    public function store(StoreStorefrontConnectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $platform = StorefrontPlatform::from($validated['platform']);
        $webhookSecret = $this->webhookSecret($platform, $validated['webhook_secret'] ?? null);
        $credentials = [];

        if (filled($validated['access_token'] ?? null) && $platform === StorefrontPlatform::Pipe17) {
            $credentials['api_key'] = $validated['access_token'];
        } elseif (filled($validated['access_token'] ?? null)) {
            $credentials['access_token'] = $validated['access_token'];
        }

        $connection = StorefrontConnection::create([
            'artist_id' => $validated['artist_id'] ?? null,
            'platform' => $platform,
            'name' => $validated['name'],
            'store_url' => $validated['store_url'] ?? null,
            'external_shop_id' => $validated['external_shop_id'] ?? null,
            'external_shop_domain' => ($validated['external_shop_domain'] ?? null) ?: $this->domainFromUrl($validated['store_url'] ?? null),
            'credentials' => $credentials,
            'oauth_scopes' => $this->defaultScopes($platform),
            'refresh_token' => ($validated['refresh_token'] ?? null) ?: null,
            'webhook_secret' => $webhookSecret,
            'status' => 'active',
            'connection_status' => StorefrontConnectionStatus::from($validated['connection_status']),
            'last_sync_meta' => [],
        ]);

        return redirect()
            ->route('admin.storefront-connections.show', $connection)
            ->with('success', 'Storefront connection created.');
    }

    public function show(StorefrontConnection $connection, StorefrontConnectionStatusService $statusService): Response
    {
        $this->authorize('viewAny', Order::class);

        $connection->load('artist:id,name');
        $connection->loadCount(['orders', 'imports']);

        return Inertia::render('Admin/StorefrontConnections/Show', [
            'connection' => $this->connectionRow($connection) + [
                'external_shop_id' => $connection->external_shop_id,
                'external_shop_domain' => $connection->external_shop_domain,
                'oauth_scopes' => $connection->oauth_scopes ?? [],
                'webhook_subscription_id' => $connection->webhook_subscription_id,
                'last_connection_error' => $connection->last_connection_error,
                'tested_at' => $connection->tested_at ? (string) $connection->tested_at : null,
                'activated_at' => $connection->activated_at ? (string) $connection->activated_at : null,
                'webhook_url' => $this->webhookUrl($connection),
            ],
            'skuChecklist' => $statusService->skuChecklist($connection),
            'testOrder' => $statusService->testOrderState($connection),
        ]);
    }

    public function test(StorefrontConnection $connection, StorefrontConnectionStatusService $statusService): RedirectResponse
    {
        $this->authorize('viewAny', Order::class);

        $statusService->markTestReceived($connection);

        return back()->with('success', 'Connection marked as test order received.');
    }

    public function activate(StorefrontConnection $connection, StorefrontConnectionStatusService $statusService): RedirectResponse
    {
        $this->authorize('viewAny', Order::class);

        $statusService->activate($connection);

        return back()->with('success', 'Connection activated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionRow(StorefrontConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'platform' => $connection->platform->value,
            'name' => $connection->name,
            'artist_name' => $connection->artist?->name,
            'store_url' => $connection->store_url,
            'status' => $connection->status,
            'connection_status' => $connection->connection_status->value,
            'last_synced_at' => $connection->last_synced_at ? (string) $connection->last_synced_at : null,
            'orders_count' => $connection->orders_count,
            'imports_count' => $connection->imports_count,
        ];
    }

    private function webhookUrl(StorefrontConnection $connection): string
    {
        return match ($connection->platform) {
            StorefrontPlatform::Shopify => route('webhooks.shopify', $connection),
            StorefrontPlatform::Squarespace => route('webhooks.squarespace', $connection),
            StorefrontPlatform::LegacyOrderDesk => '',
            StorefrontPlatform::Pipe17 => '',
        };
    }

    /**
     * @return list<string>
     */
    private function defaultScopes(StorefrontPlatform $platform): array
    {
        return match ($platform) {
            StorefrontPlatform::Shopify => array_values(config('services.shopify_connect.scopes', [])),
            StorefrontPlatform::Squarespace => array_values(config('services.squarespace_connect.scopes', [])),
            StorefrontPlatform::LegacyOrderDesk => [],
            StorefrontPlatform::Pipe17 => [],
        };
    }

    private function domainFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: null;
    }

    private function webhookSecret(StorefrontPlatform $platform, ?string $providedSecret): ?string
    {
        if ($providedSecret) {
            return $providedSecret;
        }

        if (in_array($platform, [StorefrontPlatform::LegacyOrderDesk, StorefrontPlatform::Pipe17], true)) {
            return null;
        }

        if ($platform === StorefrontPlatform::Shopify && filled(config('services.shopify_connect.client_secret'))) {
            return (string) config('services.shopify_connect.client_secret');
        }

        return Str::random(48);
    }
}
