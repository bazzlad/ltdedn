<?php

namespace App\Services;

use App\Enums\ProductEditionStatus;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Str;

class ProductSkuService
{
    public function ensureDefaultSku(Product $product): ProductSku
    {
        $existingSku = $product->skus()
            ->orderBy('sku_code')
            ->first();

        if ($existingSku) {
            return $existingSku;
        }

        return ProductSku::create([
            'product_id' => $product->id,
            'sku_code' => $this->uniqueDefaultSkuCode($product),
            'price_amount' => $this->priceAmount($product),
            'currency' => 'gbp',
            'stock_on_hand' => $this->availableEditionCount($product),
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => [],
        ]);
    }

    public function syncSingleSkuStockFromEditions(Product $product): void
    {
        $sku = $this->ensureDefaultSku($product);

        if (! $product->is_limited) {
            return;
        }

        if ($product->skus()->count() !== 1) {
            return;
        }

        $sku->forceFill([
            'stock_on_hand' => $this->availableEditionCount($product),
            'stock_reserved' => 0,
        ])->save();
    }

    public function defaultSkuCode(Product $product): string
    {
        $slug = Str::slug($product->slug ?: $product->name);
        $slug = $slug === '' ? 'product' : $slug;

        return Str::upper("LTD-{$product->id}-{$slug}");
    }

    private function uniqueDefaultSkuCode(Product $product): string
    {
        $base = $this->defaultSkuCode($product);
        $candidate = $base;
        $suffix = 2;

        while (ProductSku::query()->where('sku_code', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function availableEditionCount(Product $product): int
    {
        return $product->editions()
            ->where('status', ProductEditionStatus::Available->value)
            ->count();
    }

    private function priceAmount(Product $product): ?int
    {
        if ($product->base_price === null) {
            return null;
        }

        return (int) round(((float) $product->base_price) * 100);
    }
}
