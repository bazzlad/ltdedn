<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductEdition extends Model
{
    /** @use HasFactory<\Database\Factories\ProductEditionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'product_editions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ProductEdition $edition) {
            if (empty($edition->qr_code)) {
                $edition->qr_code = Str::uuid();
            }

            if (empty($edition->qr_short_code)) {
                $edition->qr_short_code = strtoupper(Str::random(6));
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isSold(): bool
    {
        return $this->status === 'sold';
    }

    public function isRedeemed(): bool
    {
        return $this->status === 'redeemed';
    }
}
