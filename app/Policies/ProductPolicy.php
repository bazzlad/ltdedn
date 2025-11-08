<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isArtist();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isArtist()) {
            return $this->canManageArtistProduct($user, $product->artist_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?int $artistId = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isArtist()) {
            return $this->canManageArtistProduct($user, $artistId);
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isArtist()) {
            return $this->canManageArtistProduct($user, $product->artist_id);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isArtist()) {
            return $this->canManageArtistProduct($user, $product->artist_id);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Product $product): bool
    {
        return $this->delete($user, $product);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $this->delete($user, $product);
    }

    protected function canManageArtistProduct(User $user, int $artistId): bool
    {
        return $user->ownedArtists()->where('id', $artistId)->exists();
    }
}
