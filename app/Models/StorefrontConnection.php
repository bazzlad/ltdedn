<?php

namespace App\Models;

use App\Enums\StorefrontConnectionStatus;
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

    protected $hidden = ['credentials', 'refresh_token', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'platform' => StorefrontPlatform::class,
            'connection_status' => StorefrontConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'oauth_scopes' => 'array',
            'refresh_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'last_sync_meta' => 'array',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'tested_at' => 'datetime',
            'activated_at' => 'datetime',
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
