<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyProducts = DB::table('products')
            ->select(['id', 'base_price', 'currency', 'is_sellable', 'sale_status'])
            ->where('sell_through_ltdedn', true)
            ->where('is_public', true)
            ->get();

        foreach ($legacyProducts as $product) {
            $hasAvailableEdition = DB::table('product_editions')
                ->where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->where('status', 'available')
                ->exists();

            if (! $hasAvailableEdition) {
                continue;
            }

            $basePrice = (float) ($product->base_price ?? 0);
            $hasValidBasePrice = $basePrice > 0;

            $hasSellableSku = DB::table('product_skus as sku')
                ->join('product_editions as edition', 'edition.product_sku_id', '=', 'sku.id')
                ->where('sku.product_id', $product->id)
                ->where('sku.is_active', true)
                ->where('sku.price_amount', '>', 0)
                ->where('edition.status', 'available')
                ->whereNull('edition.deleted_at')
                ->exists();

            if (! $hasValidBasePrice && ! $hasSellableSku) {
                continue;
            }

            $updates = [];

            if (! $product->is_sellable) {
                $updates['is_sellable'] = true;
            }

            if ($product->sale_status === 'draft') {
                $updates['sale_status'] = 'active';
            }

            if (! $product->currency) {
                $updates['currency'] = 'gbp';
            }

            if (! empty($updates)) {
                DB::table('products')->where('id', $product->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Intentional no-op. This migration only backfills legacy data safely.
    }
};
