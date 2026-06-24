<?php

namespace App\Models;

use App\Enums\ExternalImportStatus;
use App\Enums\StorefrontPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalOrderImport extends Model
{
    /** @use HasFactory<\Database\Factories\ExternalOrderImportFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'platform' => StorefrontPlatform::class,
            'status' => ExternalImportStatus::class,
            'raw_payload' => 'encrypted:array',
            'processed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(StorefrontConnection::class, 'storefront_connection_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
