<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductEditionTransfer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ProductEditionTransfer $transfer) {
            if (empty($transfer->token)) {
                $transfer->token = Str::random(64);
            }
        });
    }

    public function productEdition(): BelongsTo
    {
        return $this->belongsTo(ProductEdition::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('status', 'expired');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function canAccept(User $user): bool
    {
        return $this->status === 'pending' && ! $this->isExpired() && $this->recipient_id === $user->id;
    }

    public function canCancel(User $user): bool
    {
        return $this->status === 'pending' && $this->sender_id === $user->id;
    }
}
