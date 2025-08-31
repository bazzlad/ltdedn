<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;

class ProductEditionPolicy
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
    public function view(User $user, ProductEdition $productEdition, Product $product): bool
    {
        return $this->canManageProduct($user, $product);
    }

    /**
     * Determine whether the user can create models for a specific product.
     */
    public function create(User $user, Product $product): bool
    {
        return $this->canManageProduct($user, $product);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductEdition $productEdition, Product $product): bool
    {
        return $this->canManageProduct($user, $product);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductEdition $productEdition, Product $product): bool
    {
        return $this->canManageProduct($user, $product);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProductEdition $productEdition, Product $product): bool
    {
        return $this->canManageProduct($user, $product);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProductEdition $productEdition, Product $product): bool
    {
        return $this->canManageProduct($user, $product);
    }

    /**
     * Helper method to determine if user can manage a product (and therefore its editions).
     */
    private function canManageProduct(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isArtist()) {
            return $user->ownedArtists()->pluck('id')->contains($product->artist_id);
        }

        return false;
    }
}
