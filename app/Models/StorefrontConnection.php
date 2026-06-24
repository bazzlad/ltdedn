<?php

namespace App\Models;

use App\Enums\StorefrontPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontConnection extends Model
{
    /** @use HasFactory<\Database\Factories\StorefrontConnectionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['credentials', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'platform' => StorefrontPlatform::class,
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'last_sync_meta' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(ExternalOrderImport::class);
    }
}
