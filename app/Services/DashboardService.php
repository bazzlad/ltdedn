<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\User;

class DashboardService
{
    public function getAdminStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_artists' => Artist::count(),
            'recent_users' => User::latest()->take(5)->get(['id', 'name', 'email', 'role', 'created_at']),
            'recent_artists' => Artist::with('owner:id,name')->latest()->take(5)->get(['id', 'name', 'slug', 'owner_id', 'created_at']),
        ];
    }

    public function getArtistStats(User $user): array
    {
        $ownedArtists = $user->ownedArtists();

        return [
            'total_artists' => $ownedArtists->count(),
            'total_products' => 0, // Will be implemented when products are created
            'recent_artists' => $ownedArtists->latest()->take(5)->get(['id', 'name', 'slug', 'owner_id', 'created_at']),
            'recent_products' => [], // Will be implemented when products are created
        ];
    }
}
