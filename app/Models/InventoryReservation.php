<?php

namespace App\Models;

use App\Enums\InventoryReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => InventoryReservationStatus::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(ProductEdition::class, 'product_edition_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }
}
