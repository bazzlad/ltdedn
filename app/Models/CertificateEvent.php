<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateEvent extends Model
{
    protected $guarded = ['id'];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(ProductEdition::class, 'edition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
