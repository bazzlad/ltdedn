<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChainToken extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'minted_at' => 'datetime',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(ProductEdition::class, 'edition_id');
    }
}
