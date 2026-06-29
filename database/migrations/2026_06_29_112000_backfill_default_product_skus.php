<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('products')
            ->select(['id', 'name', 'slug', 'base_price'])
            ->whereNull('deleted_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('product_skus')
                    ->whereColumn('product_skus.product_id', 'products.id');
            })
            ->orderBy('id')
            ->chunk(100, function ($products) use ($now): void {
                foreach ($products as $product) {
                    DB::table('product_skus')->insert([
                        'product_id' => $product->id,
                        'sku_code' => $this->uniqueSkuCode($this->defaultSkuCode($product)),
                        'price_amount' => $product->base_price === null ? null : (int) round(((float) $product->base_price) * 100),
                        'currency' => 'gbp',
                        'stock_on_hand' => $this->availableEditionCount((int) $product->id),
                        'stock_reserved' => 0,
                        'is_active' => true,
                        'attributes' => json_encode([]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Do not delete SKUs on rollback. Once exposed to Shopify, SKU codes can
        // be copied into storefronts and should not be removed automatically.
    }

    private function defaultSkuCode(object $product): string
    {
        $slug = Str::slug($product->slug ?: $product->name);
        $slug = $slug === '' ? 'product' : $slug;

        return Str::upper("LTD-{$product->id}-{$slug}");
    }

    private function uniqueSkuCode(string $base): string
    {
        $candidate = $base;
        $suffix = 2;

        while (DB::table('product_skus')->where('sku_code', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function availableEditionCount(int $productId): int
    {
        return DB::table('product_editions')
            ->where('product_id', $productId)
            ->where('status', 'available')
            ->whereNull('deleted_at')
            ->count();
    }
};
