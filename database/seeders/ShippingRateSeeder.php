<?php

namespace Database\Seeders;

use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

class ShippingRateSeeder extends Seeder
{
    public function run(): void
    {
        ShippingRate::query()->updateOrCreate(
            ['code' => 'uk-standard'],
            [
                'label' => 'UK Standard (3–5 working days)',
                'currency' => 'gbp',
                'amount_minor' => 495,
                'country_codes' => ['GB'],
                'is_active' => true,
                'sort_order' => 10,
                'stripe_rate_id' => null,
            ],
        );

        ShippingRate::query()->updateOrCreate(
            ['code' => 'international'],
            [
                'label' => 'International',
                'currency' => 'gbp',
                'amount_minor' => 1500,
                'country_codes' => null,
                'is_active' => true,
                'sort_order' => 90,
                'stripe_rate_id' => null,
            ],
        );
    }
}
