<?php

namespace App\Http\Controllers\Connect;

use App\Enums\StorefrontConnectionStatus;
use App\Enums\StorefrontPlatform;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\StorefrontConnection;
use App\Services\StorefrontConnect\SquarespaceConnectorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SquarespaceConnectionController extends Controller
{
    public function start(Request $request, SquarespaceConnectorService $squarespace): RedirectResponse
    {
        $validated = $request->validate([
            'artist_id' => ['required', 'exists:artists,id'],
            'website_id' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $artist = Artist::findOrFail($validated['artist_id']);
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $artist->isOwner($user) || $artist->hasTeamMember($user)), 403);

        if (! $squarespace->isConfigured()) {
            return back()->withErrors(['squarespace' => 'Squarespace Connect is not configured yet.']);
        }

        $state = Str::random(40);

        $request->session()->put('connect.squarespace', [
            'state' => $state,
            'artist_id' => $artist->id,
            'website_id' => $validated['website_id'] ?? null,
            'name' => $validated['name'] ?? $artist->name.' Squarespace',
        ]);

        return redirect()->away($squarespace->authorizationUrl($state, $validated['website_id'] ?? null));
    }

    public function callback(Request $request, SquarespaceConnectorService $squarespace): RedirectResponse
    {
        $pending = $request->session()->pull('connect.squarespace');

        if ($request->query('error')) {
            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['squarespace' => 'Squarespace authorization was cancelled.']);
        }

        if (! is_array($pending) || ! hash_equals((string) ($pending['state'] ?? ''), (string) $request->query('state'))) {
            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['squarespace' => 'Squarespace authorization expired. Please try again.']);
        }

        $artist = Artist::findOrFail($pending['artist_id']);
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $artist->isOwner($user) || $artist->hasTeamMember($user)), 403);

        try {
            $token = $squarespace->exchangeCode((string) $request->query('code'));
        } catch (Throwable $exception) {
            Log::warning('Squarespace Connect token exchange failed.', [
                'artist_id' => $artist->id,
                'website_id' => $pending['website_id'] ?? null,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['squarespace' => 'Squarespace authorization failed. Please try again or contact LTD EDN support.']);
        }

        $connection = $this->findExistingConnection($pending);

        if ($connection && $connection->artist_id !== $artist->id) {
            return redirect()
                ->route('connect.storefronts')
                ->withErrors(['squarespace' => 'This Squarespace site is already connected to another artist.']);
        }

        $connection ??= new StorefrontConnection([
            'platform' => StorefrontPlatform::Squarespace,
        ]);

        $connection->forceFill([
            'artist_id' => $artist->id,
            'name' => $pending['name'],
            'external_shop_id' => $pending['website_id'],
            'credentials' => ['access_token' => $token['access_token']],
            'oauth_scopes' => array_values(config('services.squarespace_connect.scopes', [])),
            'token_expires_at' => $token['token_expires_at'],
            'refresh_token' => $token['refresh_token'],
            'webhook_secret' => Str::random(48),
            'status' => 'active',
            'connection_status' => StorefrontConnectionStatus::WebhookReady,
            'last_sync_meta' => [],
        ])->save();

        try {
            $webhook = $squarespace->registerOrderWebhook($connection);
            $websiteId = data_get($webhook, 'websiteId', $connection->external_shop_id);

            if ($websiteId) {
                $duplicate = StorefrontConnection::query()
                    ->where('platform', StorefrontPlatform::Squarespace->value)
                    ->where('external_shop_id', $websiteId)
                    ->whereKeyNot($connection->id)
                    ->first();

                if ($duplicate) {
                    $connection->delete();

                    return redirect()
                        ->route('connect.storefronts')
                        ->withErrors(['squarespace' => 'This Squarespace site is already connected.']);
                }
            }

            $connection->forceFill([
                'external_shop_id' => $websiteId,
                'webhook_subscription_id' => data_get($webhook, 'id'),
                'webhook_secret' => data_get($webhook, 'secret', $connection->webhook_secret),
                'connection_status' => StorefrontConnectionStatus::Testing,
                'last_connection_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Squarespace Connect webhook registration failed.', [
                'connection_id' => $connection->id,
                'artist_id' => $artist->id,
                'website_id' => $pending['website_id'] ?? null,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $connection->forceFill([
                'connection_status' => StorefrontConnectionStatus::Failed,
                'last_connection_error' => 'Squarespace webhook registration failed. Please contact LTD EDN support.',
            ])->save();
        }

        return redirect()->route('connect.storefronts.check', $connection);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function findExistingConnection(array $pending): ?StorefrontConnection
    {
        if (filled($pending['website_id'] ?? null)) {
            $connection = StorefrontConnection::query()
                ->where('platform', StorefrontPlatform::Squarespace->value)
                ->where('external_shop_id', $pending['website_id'])
                ->first();

            if ($connection) {
                return $connection;
            }
        }

        return StorefrontConnection::query()
            ->where('platform', StorefrontPlatform::Squarespace->value)
            ->where('name', $pending['name'])
            ->first();
    }
}
