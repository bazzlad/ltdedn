<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ProductSku extends Model
{
    /** @use HasFactory<\Database\Factories\ProductSkuFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['stock_available'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active' => 'boolean',
            'price_amount' => 'integer',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function editions(): HasMany
    {
        return $this->hasMany(ProductEdition::class, 'product_sku_id');
    }

    public function getStockAvailableAttribute(): int
    {
        return max(0, $this->stock_on_hand - $this->stock_reserved);
    }

    public function allocatePaidQuantity(int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $updated = self::query()
            ->where('id', $this->id)
            ->whereRaw('stock_on_hand - stock_reserved >= ?', [$quantity])
            ->update([
                'stock_on_hand' => DB::raw('stock_on_hand - '.(int) $quantity),
            ]);

        $this->refresh();

        return $updated > 0;
    }
}
