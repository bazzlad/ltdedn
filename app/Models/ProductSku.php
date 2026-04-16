<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSku extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['stock_available'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'attributes' => 'array',
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

    public function variantValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariantValue::class,
            'product_sku_variant_values',
            'product_sku_id',
            'product_variant_value_id',
        )->withPivot('product_variant_axis_id')->withTimestamps();
    }

    public function getStockAvailableAttribute(): int
    {
        $available = (int) $this->stock_on_hand - (int) $this->stock_reserved;

        return $available > 0 ? $available : 0;
    }

    public function reserve(int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $updated = self::query()
            ->where('id', $this->id)
            ->whereRaw('stock_on_hand - stock_reserved >= ?', [$quantity])
            ->increment('stock_reserved', $quantity);

        $this->refresh();

        return $updated > 0;
    }

    public function release(int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $updated = self::query()
            ->where('id', $this->id)
            ->where('stock_reserved', '>=', $quantity)
            ->decrement('stock_reserved', $quantity);

        $this->refresh();

        return $updated > 0;
    }

    public function consume(int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $updated = self::query()
            ->where('id', $this->id)
            ->where('stock_reserved', '>=', $quantity)
            ->where('stock_on_hand', '>=', $quantity)
            ->update([
                'stock_reserved' => \Illuminate\Support\Facades\DB::raw('stock_reserved - '.(int) $quantity),
                'stock_on_hand' => \Illuminate\Support\Facades\DB::raw('stock_on_hand - '.(int) $quantity),
            ]);

        $this->refresh();

        return $updated > 0;
    }
}
