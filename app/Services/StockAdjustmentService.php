<?php

namespace App\Services;

use App\Models\ProductSku;
use App\Models\SkuStockAdjustment;

class StockAdjustmentService
{
    public function record(
        ProductSku $sku,
        int $deltaOnHand,
        int $beforeOnHand,
        int $afterOnHand,
        string $reason,
        string $source,
        ?int $actorUserId = null,
        array $meta = []
    ): void {
        SkuStockAdjustment::create([
            'product_sku_id' => $sku->id,
            'actor_user_id' => $actorUserId,
            'delta_on_hand' => $deltaOnHand,
            'before_on_hand' => $beforeOnHand,
            'after_on_hand' => $afterOnHand,
            'reason' => $reason,
            'source' => $source,
            'meta' => $meta,
        ]);
    }
}
