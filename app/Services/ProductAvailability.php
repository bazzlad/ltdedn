<?php

namespace App\Services;

use App\Enums\ProductEditionStatus;
use App\Enums\ProductSaleStatus;
use App\Models\Product;
use App\Models\ProductSku;

class ProductAvailability
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public static function assertPurchasable(Product $product, ?ProductSku $sku, ?int $userId): array
    {
        if (! $product->sell_through_ltdedn || ! $product->is_sellable || $product->sale_status !== ProductSaleStatus::Active) {
            return ['ok' => false, 'error' => 'This product is not currently available for purchase.'];
        }

        if (! $product->is_public) {
            return ['ok' => false, 'error' => 'This product is not currently available for purchase.'];
        }

        if ($sku && ! $sku->is_active) {
            return ['ok' => false, 'error' => 'This product variant is not currently available for purchase.'];
        }

        if ($sku && (int) $sku->product_id !== (int) $product->id) {
            return ['ok' => false, 'error' => 'Selected variant does not belong to this product.'];
        }

        if (self::resolvePrice($product, $sku) < 1) {
            return ['ok' => false, 'error' => 'This product has no valid price set.'];
        }

        return ['ok' => true];
    }

    public static function resolvePrice(Product $product, ?ProductSku $sku): int
    {
        if ($sku) {
            return (int) $sku->price_amount;
        }

        return (int) round(((float) $product->base_price) * 100);
    }

    public static function resolveCurrency(Product $product, ?ProductSku $sku): string
    {
        if ($sku && $sku->currency) {
            return (string) $sku->currency;
        }

        return (string) ($product->currency ?: 'gbp');
    }

    /**
     * Count editions currently available to sell for this product/SKU combo.
     *
     * Matches the figure shown on the product page and the cap enforced by
     * CheckoutService: available editions, minus any held by a live (not
     * yet expired) reservation — regardless of which cart placed them.
     */
    public static function availableForLine(Product $product, ?ProductSku $sku): int
    {
        $query = $product->editions()
            ->where('status', ProductEditionStatus::Available)
            ->whereNotIn('id', function ($q) {
                $q->select('product_edition_id')
                    ->from('inventory_reservations')
                    ->where('status', 'active')
                    ->whereNotNull('product_edition_id')
                    ->where('expires_at', '>', now());
            });

        if ($sku) {
            $query->where('product_sku_id', $sku->id);
        } else {
            $query->whereNull('product_sku_id');
        }

        return (int) $query->count();
    }
}
