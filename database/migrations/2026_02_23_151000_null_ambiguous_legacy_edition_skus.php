<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy safety backfill:
        // If a product has multiple SKUs and all historical editions were auto-mapped
        // to the first SKU, reset those editions to standard (null SKU) so they remain sellable
        // without forcing a wrong variant mapping.
        $productIds = DB::table('product_skus')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_id');

        foreach ($productIds as $productId) {
            $minSkuId = (int) DB::table('product_skus')
                ->where('product_id', $productId)
                ->min('id');

            if ($minSkuId < 1) {
                continue;
            }

            $hasDifferentSkuAssignments = DB::table('product_editions')
                ->where('product_id', $productId)
                ->whereNotNull('product_sku_id')
                ->where('product_sku_id', '!=', $minSkuId)
                ->exists();

            if ($hasDifferentSkuAssignments) {
                continue;
            }

            $hasCommittedSales = DB::table('order_items')
                ->where('product_id', $productId)
                ->whereNotNull('product_sku_id')
                ->exists();

            if ($hasCommittedSales) {
                continue;
            }

            DB::table('product_editions')
                ->where('product_id', $productId)
                ->where('product_sku_id', $minSkuId)
                ->update(['product_sku_id' => null]);
        }
    }

    public function down(): void
    {
        // No-op. This migration is intentionally one-way data healing.
    }
};
