<?php

use App\Http\Controllers\Webhooks\ShopifyWebhookController;
use App\Http\Controllers\Webhooks\SquarespaceWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/shopify/{connection}', ShopifyWebhookController::class)
    ->name('webhooks.shopify');

Route::post('webhooks/squarespace/{connection}', SquarespaceWebhookController::class)
    ->name('webhooks.squarespace');
