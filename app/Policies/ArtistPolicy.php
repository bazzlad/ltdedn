<?php

namespace App\Policies;

use App\Models\Artist;
use App\Models\User;

class ArtistPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->ownedArtists()->exists() || $user->artistTeams()->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Artist $artist): bool
    {
        return $user->isAdmin() || $artist->isOwner($user) || $artist->hasTeamMember($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isArtist();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Artist $artist): bool
    {
        return $user->isAdmin() || $artist->isOwner($user) || $artist->hasTeamMember($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Artist $artist): bool
    {
        return $user->isAdmin() || $artist->isOwner($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Artist $artist): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Artist $artist): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage the artist's team.
     */
    public function manageTeam(User $user, Artist $artist): bool
    {
        return $user->isAdmin() || $artist->isOwner($user);
    }
}
