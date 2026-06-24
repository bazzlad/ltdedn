<?php

namespace App\Http\Controllers\Connect;

use App\Enums\StorefrontConnectionStatus;
use App\Enums\StorefrontPlatform;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\StorefrontConnection;
use App\Services\StorefrontConnect\ShopifyConnectorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ShopifyConnectionController extends Controller
{
    public function start(Request $request, ShopifyConnectorService $shopify): RedirectResponse
    {
        $validated = $request->validate([
            'artist_id' => ['required', 'exists:artists,id'],
            'shop' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $artist = Artist::findOrFail($validated['artist_id']);
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $artist->isOwner($user) || $artist->hasTeamMember($user)), 403);

        if (! $shopify->isConfigured()) {
            return back()->withErrors(['shopify' => 'Shopify Connect is not configured yet.']);
        }

        try {
            $shopDomain = $shopify->normalizeShopDomain($validated['shop']);
        } catch (Throwable $exception) {
            return back()->withErrors(['shop' => $exception->getMessage()]);
        }

        $state = Str::random(40);

        $request->session()->put('connect.shopify', [
            'state' => $state,
            'artist_id' => $artist->id,
            'shop' => $shopDomain,
            'name' => $validated['name'] ?? Str::headline(Str::before($shopDomain, '.myshopify.com')),
        ]);

        return redirect()->away($shopify->authorizationUrl($shopDomain, $state));
    }

    public function callback(Request $request, ShopifyConnectorService $shopify): RedirectResponse
    {
        $pending = $request->session()->pull('connect.shopify');

        if ($request->query('error')) {
            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['shopify' => 'Shopify authorization was cancelled.']);
        }

        if (! is_array($pending) || ! hash_equals((string) ($pending['state'] ?? ''), (string) $request->query('state'))) {
            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['shopify' => 'Shopify authorization expired. Please try again.']);
        }

        if (! $shopify->verifyCallback($request)) {
            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['shopify' => 'Shopify authorization could not be verified.']);
        }

        $artist = Artist::findOrFail($pending['artist_id']);
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $artist->isOwner($user) || $artist->hasTeamMember($user)), 403);

        $shopDomain = $shopify->normalizeShopDomain((string) $request->query('shop', $pending['shop']));
        $connection = StorefrontConnection::query()
            ->where('platform', StorefrontPlatform::Shopify->value)
            ->where('external_shop_domain', $shopDomain)
            ->first();

        if ($connection && $connection->artist_id !== $artist->id) {
            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['shopify' => 'This Shopify store is already connected to another artist.']);
        }

        try {
            $token = $shopify->exchangeCode($shopDomain, (string) $request->query('code'));
        } catch (Throwable $exception) {
            Log::warning('Shopify Connect token exchange failed.', [
                'shop' => $shopDomain,
                'artist_id' => $artist->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['shopify' => 'Shopify authorization failed. Please try again or contact LTD EDN support.']);
        }

        $connection ??= new StorefrontConnection([
            'platform' => StorefrontPlatform::Shopify,
            'external_shop_domain' => $shopDomain,
        ]);

        $connection->forceFill([
            'artist_id' => $artist->id,
            'name' => $pending['name'],
            'store_url' => 'https://'.$shopDomain,
            'credentials' => ['access_token' => $token['access_token']],
            'oauth_scopes' => $token['scope'] ? array_values(array_filter(explode(',', $token['scope']))) : array_values(config('services.shopify_connect.scopes', [])),
            'webhook_secret' => config('services.shopify_connect.client_secret'),
            'status' => 'active',
            'connection_status' => StorefrontConnectionStatus::WebhookReady,
            'last_connection_error' => null,
            'last_sync_meta' => [],
        ])->save();

        try {
            $webhook = $shopify->registerOrderWebhook($connection);

            $connection->forceFill([
                'webhook_subscription_id' => data_get($webhook, 'webhook.id'),
                'connection_status' => StorefrontConnectionStatus::Testing,
                'last_connection_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Shopify Connect webhook registration failed.', [
                'connection_id' => $connection->id,
                'shop' => $shopDomain,
                'artist_id' => $artist->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $connection->forceFill([
                'connection_status' => StorefrontConnectionStatus::Failed,
                'last_connection_error' => 'Shopify webhook registration failed. Please contact LTD EDN support.',
            ])->save();
        }

        return redirect()->route('connect.storefronts.check', $connection);
    }
}
