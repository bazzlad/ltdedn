<?php

use App\Http\Controllers\AcceptProductEditionTransferController;
use App\Http\Controllers\ArtistsController;
use App\Http\Controllers\CancelProductEditionTransferController;
use App\Http\Controllers\ClaimQRController;
use App\Http\Controllers\InvestController;
use App\Http\Controllers\PasswordGateController;
use App\Http\Controllers\ProductEditionTransferController;
use App\Http\Controllers\RejectProductEditionTransferController;
use App\Http\Controllers\ShowQRController;
use App\Http\Controllers\TransferQRController;
use App\Http\Controllers\UserDashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('dashboard', UserDashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Password Gate Routes
|--------------------------------------------------------------------------
*/

Route::get('/password-gate', [PasswordGateController::class, 'show'])->name('password-gate.show');
Route::post('/password-gate', [PasswordGateController::class, 'store'])->name('password-gate.store');

/*
|--------------------------------------------------------------------------
| Password Protected Pages
|--------------------------------------------------------------------------
*/

Route::middleware('password')->group(function () {
    Route::get('/artists', ArtistsController::class)->name('artists');
});

Route::middleware('password')->group(function () {
    Route::get('/invest', InvestController::class)->name('invest');
});

/*
|--------------------------------------------------------------------------
| QR Code Routes
|--------------------------------------------------------------------------
*/

Route::get('/qr/{qrCode}', ShowQRController::class)->name('qr.show');
Route::post('/qr/{qrCode}/claim', ClaimQRController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('qr.claim');
Route::post('/qr/{qrCode}/transfer', TransferQRController::class)
    ->middleware(['auth', 'throttle:5,1'])
    ->name('qr.transfer');

/*
|--------------------------------------------------------------------------
| Transfer Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::get('/product-edition-transfers/{token}/accept', [ProductEditionTransferController::class, 'show'])->name('transfers.accept');
    Route::post('/product-edition-transfers/{token}/accept', AcceptProductEditionTransferController::class)->name('transfers.accept.post');
    Route::post('/product-edition-transfers/{token}/reject', RejectProductEditionTransferController::class)->name('transfers.reject');
    Route::post('/product-edition-transfers/{token}/cancel', CancelProductEditionTransferController::class)->name('transfers.cancel');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
