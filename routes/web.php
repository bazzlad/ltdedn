<?php

use App\Http\Controllers\ClaimQRController;
use App\Http\Controllers\ShowQRController;
use App\Http\Controllers\TransferQRController;
use App\Http\Controllers\UserDashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
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
| QR Code Routes
|--------------------------------------------------------------------------
*/

Route::get('/qr/{qrCode}', ShowQRController::class)->name('qr.show');
Route::post('/qr/{qrCode}/claim', ClaimQRController::class)->middleware('auth')->name('qr.claim');
Route::post('/qr/{qrCode}/transfer', TransferQRController::class)->middleware('auth')->name('qr.transfer');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
