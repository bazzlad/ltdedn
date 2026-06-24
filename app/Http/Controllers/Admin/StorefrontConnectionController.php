<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StorefrontConnection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontConnectionController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', \App\Models\Order::class);

        $connections = StorefrontConnection::query()
            ->with('artist:id,name')
            ->withCount(['orders', 'imports'])
            ->orderBy('platform')
            ->orderBy('name')
            ->get()
            ->map(fn (StorefrontConnection $connection) => [
                'id' => $connection->id,
                'platform' => $connection->platform->value,
                'name' => $connection->name,
                'artist_name' => $connection->artist?->name,
                'store_url' => $connection->store_url,
                'status' => $connection->status,
                'last_synced_at' => $connection->last_synced_at ? (string) $connection->last_synced_at : null,
                'orders_count' => $connection->orders_count,
                'imports_count' => $connection->imports_count,
            ])
            ->values();

        return Inertia::render('Admin/StorefrontConnections/Index', [
            'connections' => $connections,
        ]);
    }
}
