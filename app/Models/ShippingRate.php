<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    /** @use HasFactory<\Database\Factories\ShippingRateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'country_codes' => 'array',
            'amount_minor' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function appliesToCountry(?string $countryCode): bool
    {
        if ($this->country_codes === null || $this->country_codes === []) {
            return true;
        }

        if ($countryCode === null) {
            return false;
        }

        $needle = strtoupper($countryCode);

        foreach ((array) $this->country_codes as $code) {
            if (strtoupper((string) $code) === $needle) {
                return true;
            }
        }

        return false;
    }
}
