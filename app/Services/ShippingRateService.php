<?php

namespace App\Services;

use App\Models\ShippingRate;
use Illuminate\Support\Collection;

class ShippingRateService
{
    /**
     * Every active shipping rate, ordered deterministically, for surfacing
     * to the buyer at checkout. Stripe shows them all and the buyer picks
     * whichever applies to their address.
     *
     * @return Collection<int, ShippingRate>
     */
    public function activeRates(): Collection
    {
        return ShippingRate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Pick the single best-fit rate for a given country, preferring a rate
     * that explicitly covers that country, then the configured default,
     * then any global-fallback rate (country_codes IS NULL).
     */
    public function resolveForCountry(?string $country): ?ShippingRate
    {
        $rates = $this->activeRates();
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
