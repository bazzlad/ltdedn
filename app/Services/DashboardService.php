<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\Product;
use App\Models\User;

class DashboardService
{
    public function getAdminStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_artists' => Artist::count(),
            'total_products' => Product::count(),
            'recent_users' => User::latest()->take(5)->get(['id', 'name', 'email', 'role', 'created_at']),
            'recent_artists' => Artist::with('owner:id,name')->latest()->take(5)->get(['id', 'name', 'slug', 'owner_id', 'created_at']),
            'recent_products' => Product::with('artist:id,name')->latest()->take(5)->get(['id', 'name', 'slug', 'is_public', 'artist_id', 'created_at']),
        ];
    }

    public function getArtistStats(User $user): array
    {
        $ownedArtists = $user->ownedArtists();
        $artistIds = $ownedArtists->pluck('id');

        return [
            'total_artists' => $ownedArtists->count(),
            'total_products' => Product::whereIn('artist_id', $artistIds)->count(),
            'recent_artists' => $ownedArtists->latest()->take(5)->get(['id', 'name', 'slug', 'owner_id', 'created_at']),
            'recent_products' => Product::with('artist:id,name')
                ->whereIn('artist_id', $artistIds)
                ->latest()
                ->take(5)
                ->get(['id', 'name', 'slug', 'is_public', 'artist_id', 'created_at']),
        ];
    }
}
