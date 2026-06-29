<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'shopify_connect' => [
        'client_id' => env('SHOPIFY_CONNECT_CLIENT_ID'),
        'client_secret' => env('SHOPIFY_CONNECT_CLIENT_SECRET'),
        'scopes' => array_filter(explode(',', env('SHOPIFY_CONNECT_SCOPES', 'read_orders,write_fulfillments,read_merchant_managed_fulfillment_orders,write_merchant_managed_fulfillment_orders'))),
        'api_version' => env('SHOPIFY_CONNECT_API_VERSION', '2025-10'),
    ],

    'squarespace_connect' => [
        'client_id' => env('SQUARESPACE_CONNECT_CLIENT_ID'),
        'client_secret' => env('SQUARESPACE_CONNECT_CLIENT_SECRET'),
        'scopes' => array_filter(explode(',', env('SQUARESPACE_CONNECT_SCOPES', 'website.orders,website.orders.read,website.products.read'))),
        'user_agent' => env('SQUARESPACE_CONNECT_USER_AGENT', env('APP_NAME', 'LTD EDN Connect')),
    ],

    'pipe17' => [
        'api_url' => env('PIPE17_API_URL', 'https://api-v3.pipe17.com/api/v3'),
        'api_key' => env('PIPE17_API_KEY'),
        'connection_id' => env('PIPE17_CONNECTION_ID'),
        'location_id' => env('PIPE17_LOCATION_ID'),
        'shipping_request_statuses' => array_filter(explode(',', env('PIPE17_SHIPPING_REQUEST_STATUSES', 'readyForFulfillment'))),
        'allowed_hosts' => array_filter(explode(',', env('PIPE17_ALLOWED_HOSTS', 'api-v3.pipe17.com,api.pipe17.com'))),
        'max_pages' => (int) env('PIPE17_MAX_PAGES', 25),
        'schedule_enabled' => env('PIPE17_SCHEDULE_ENABLED', false),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
