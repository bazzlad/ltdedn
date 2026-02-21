<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sell_through_ltdedn' => 'boolean',
            'is_limited' => 'boolean',
            'is_public' => 'boolean',
            'edition_size' => 'integer',
            'base_price' => 'decimal:2',
            'variants_schema' => 'array',
            'physical' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }

            if (empty($product->qr_secret)) {
                $product->qr_secret = Str::random(32);
            }
        });

        static::forceDeleting(function (Product $product) {
            if ($product->getRawOriginal('cover_image') && Storage::disk('public')->exists($product->getRawOriginal('cover_image'))) {
                Storage::disk('public')->delete($product->getRawOriginal('cover_image'));
            }
        });
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function editions(): HasMany
    {
        return $this->hasMany(ProductEdition::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function availableEditions(): HasMany
    {
        return $this->editions()->where('status', 'available');
    }

    public function soldEditions(): HasMany
    {
        return $this->editions()->where('status', 'sold');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
