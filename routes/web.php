<?php

use App\Http\Controllers\AcceptProductEditionTransferController;
use App\Http\Controllers\ArtistsController;
use App\Http\Controllers\CancelProductEditionTransferController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClaimQRController;
use App\Http\Controllers\CreateCheckoutSessionController;
use App\Http\Controllers\InvestController;
use App\Http\Controllers\PasswordGateController;
use App\Http\Controllers\ProductEditionTransferController;
use App\Http\Controllers\RedeemEditionController;
use App\Http\Controllers\RejectProductEditionTransferController;
use App\Http\Controllers\ShopCheckoutResultController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopProductController;
use App\Http\Controllers\ShowQRController;
use App\Http\Controllers\TokenMetadataController;
use App\Http\Controllers\TransferQRController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Controllers\VerifyEditionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [PasswordGateController::class, 'show'])->name('home');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('dashboard', UserDashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::post('/', [PasswordGateController::class, 'store'])->name('password-gate.store');

Route::middleware('password:artist')->group(function () {
    Route::get('/artist', ArtistsController::class)->name('artist');
});

Route::middleware('password:invest')->group(function () {
    Route::get('/invest', InvestController::class)->name('invest');
});

Route::get('/shop', ShopController::class)->name('shop.index');
Route::get('/shop/{artistId}/{productId}', [ShopProductController::class, 'byId'])->whereNumber('artistId')->whereNumber('productId')->name('shop.product');
Route::get('/shop/{artistSlug}/{productSlug}', [ShopProductController::class, 'bySlug'])
    ->where('artistSlug', '.*[A-Za-z].*')
    ->where('productSlug', '.*[A-Za-z].*')
    ->name('shop.product.slug');
Route::post('/shop/checkout', CreateCheckoutSessionController::class)->middleware('throttle:10,1')->name('shop.checkout');
Route::get('/shop/success/{order}', [ShopCheckoutResultController::class, 'success'])->name('shop.success');
Route::get('/shop/cancel/{order}', [ShopCheckoutResultController::class, 'cancel'])->name('shop.cancel');

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::delete('/cart', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
    Route::patch('/cart/items/{cartItem}', [CartItemController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');
});

Route::get('/qr/{qrCode}', ShowQRController::class)->name('qr.show');
Route::post('/qr/{qrCode}/claim', ClaimQRController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('qr.claim');
Route::post('/qr/{qrCode}/transfer', TransferQRController::class)
    ->middleware(['auth', 'throttle:5,1'])
    ->name('qr.transfer');
Route::post('/qr/{qrCode}/redeem', RedeemEditionController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('qr.redeem');

Route::get('/verify/{qrCode}', VerifyEditionController::class)->name('verify.qr');
Route::get('/metadata/{tokenId}.json', TokenMetadataController::class)->name('token.metadata');
Route::get('/certificate/{edition}.pdf', CertificateController::class)
    ->middleware('auth')
    ->name('certificate.pdf');

Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::get('/product-edition-transfers/{token}/accept', [ProductEditionTransferController::class, 'show'])->name('transfers.accept');
    Route::post('/product-edition-transfers/{token}/accept', AcceptProductEditionTransferController::class)->name('transfers.accept.post');
    Route::post('/product-edition-transfers/{token}/reject', RejectProductEditionTransferController::class)->name('transfers.reject');
    Route::post('/product-edition-transfers/{token}/cancel', CancelProductEditionTransferController::class)->name('transfers.cancel');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
