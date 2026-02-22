<?php

namespace App\Models;

use App\Enums\ProductEditionStatus;
use App\Services\QRCodeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
            'status' => ProductEditionStatus::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ProductEdition $edition) {
            if (empty($edition->qr_code)) {
                $qrService = app(QRCodeService::class);
                $edition->qr_code = $qrService->generateQRCode();
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
        return $this->status === ProductEditionStatus::Available;
    }

    public function isSold(): bool
    {
        return $this->status === ProductEditionStatus::Sold;
    }

    public function isRedeemed(): bool
    {
        return $this->status === ProductEditionStatus::Redeemed;
    }

    public function chainToken(): HasOne
    {
        return $this->hasOne(ChainToken::class, 'edition_id');
    }
}
