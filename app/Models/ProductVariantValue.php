<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantValue extends Model
{
    protected $guarded = ['id'];

    public function axis(): BelongsTo
    {
        return $this->belongsTo(ProductVariantAxis::class, 'product_variant_axis_id');
    }
}
