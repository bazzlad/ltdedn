<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'meta' => 'array',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'last_refunded_at' => 'datetime',
            'last_pushback_attempted_at' => 'datetime',
            'subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'refunded_amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(StorefrontConnection::class, 'storefront_connection_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function importRecords(): HasMany
    {
        return $this->hasMany(ExternalOrderImport::class);
    }

    public function isException(): bool
    {
        return $this->status === OrderStatus::Exception || $this->exception_reason !== null;
    }
}
