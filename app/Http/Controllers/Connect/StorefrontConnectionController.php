<?php

namespace App\Http\Controllers\Connect;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\StorefrontConnection;
use App\Models\User;
use App\Services\StorefrontConnect\StorefrontConnectionStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontConnectionController extends Controller
{
    public function index(Request $request): Response
    {
        $artistIds = $this->availableArtistIds($request->user());

        return Inertia::render('Connect/Storefronts', [
            'artists' => Artist::query()
                ->select(['id', 'name'])
                ->whereIn('id', $artistIds)
                ->orderBy('name')
                ->get(),
            'connections' => StorefrontConnection::query()
                ->with('artist:id,name')
                ->whereIn('artist_id', $artistIds)
                ->latest()
                ->get()
                ->map(fn (StorefrontConnection $connection) => [
                    'id' => $connection->id,
                    'platform' => $connection->platform->value,
                    'name' => $connection->name,
                    'artist_name' => $connection->artist?->name,
                    'connection_status' => $connection->connection_status->value,
                    'tested_at' => $connection->tested_at ? (string) $connection->tested_at : null,
                    'activated_at' => $connection->activated_at ? (string) $connection->activated_at : null,
                ])
                ->values(),
        ]);
    }

    public function check(Request $request, StorefrontConnection $connection, StorefrontConnectionStatusService $statusService): Response
    {
        abort_unless($this->availableArtistIds($request->user())->contains($connection->artist_id), 403);

        $connection->load('artist:id,name');

        return Inertia::render('Connect/Check', [
            'connection' => [
                'id' => $connection->id,
                'platform' => $connection->platform->value,
                'name' => $connection->name,
                'artist_name' => $connection->artist?->name,
                'connection_status' => $connection->connection_status->value,
                'last_connection_error' => $connection->last_connection_error,
                'tested_at' => $connection->tested_at ? (string) $connection->tested_at : null,
                'activated_at' => $connection->activated_at ? (string) $connection->activated_at : null,
            ],
            'skuChecklist' => $statusService->skuChecklist($connection),
            'testOrder' => $statusService->testOrderState($connection),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function availableArtistIds(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if ($user->isAdmin()) {
            return Artist::query()->pluck('id');
        }

        return $user->ownedArtists()
            ->pluck('id')
            ->merge($user->artistTeams()->pluck('artists.id'))
            ->unique()
            ->values();
    }
}
