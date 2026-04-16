<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reservation TTL
    |--------------------------------------------------------------------------
    |
    | How long a Stripe checkout session (and its linked inventory reservations)
    | stay alive before being expired by shop:expire-reservations.
    |
    */

    'reservation_ttl_minutes' => (int) env('SHOP_RESERVATION_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Admin notifications
    |--------------------------------------------------------------------------
    |
    | Email address that receives new-order notifications on Paid transition.
    |
    */

    'admin_notification_email' => env('SHOP_ADMIN_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | Default shipping-rate code used when no country is yet known (shown
    | to the buyer pre-address). Must match a ShippingRate row's `code`.
    | Allowed countries are the ISO-3166 alpha-2 codes Stripe will collect
    | addresses for.
    |
    */

    'default_shipping_rate_code' => env('SHOP_DEFAULT_SHIPPING_RATE_CODE', 'uk-standard'),

    'allowed_shipping_countries' => [
        'GB', 'IE', 'FR', 'DE', 'NL', 'BE', 'ES', 'IT', 'PT', 'AT', 'DK',
        'SE', 'NO', 'FI', 'PL', 'CZ', 'US', 'CA', 'AU', 'NZ', 'JP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    |
    | Default Stripe tax code assigned to line items when the product has
    | no specific override. "txcd_99999999" = general, tangible goods.
    |
    */

    'default_tax_code' => env('SHOP_DEFAULT_TAX_CODE', 'txcd_99999999'),

];
