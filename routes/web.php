<?php

use App\Http\Controllers\ClaimQRController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShowQRController;
use App\Http\Controllers\TransferQRController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

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
