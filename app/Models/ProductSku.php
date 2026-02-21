<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

        $this->refresh();

        if ((int) $this->stock_reserved < $quantity || (int) $this->stock_on_hand < $quantity) {
            return false;
        }

        $this->stock_reserved = (int) $this->stock_reserved - $quantity;
        $this->stock_on_hand = (int) $this->stock_on_hand - $quantity;

        return $this->save();
    }
}
