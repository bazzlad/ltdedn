<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ShippingRate;

class ShippingRateService
{
    /**
     * Resolve the applicable shipping rate for a cart. If `$country` is
     * provided, prefer an active rate covering that country; otherwise fall
     * back to the default code from config, then any active rate with no
     * country filter (global fallback).
     */
    public function resolveForCart(Cart $cart, ?string $country = null): ?ShippingRate
    {
        $rates = ShippingRate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rates->isEmpty()) {
            return null;
        }

        if ($country) {
            foreach ($rates as $rate) {
                if ($rate->appliesToCountry($country)) {
                    return $rate;
                }
            }
        }

        $defaultCode = (string) config('shop.default_shipping_rate_code', '');
        if ($defaultCode !== '') {
            $default = $rates->firstWhere('code', $defaultCode);
            if ($default) {
                return $default;
            }
        }

        return $rates->first(fn (ShippingRate $r) => $r->country_codes === null || $r->country_codes === [])
            ?? $rates->first();
    }
}
