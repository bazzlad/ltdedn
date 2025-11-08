<?php

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductEditionBulkController;
use App\Http\Controllers\Admin\ProductEditionController;
use App\Http\Controllers\Admin\ProductEditionQrBatchPdfController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,artist'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('products', ProductController::class);

        Route::resource('products.editions', ProductEditionController::class)
            ->except(['show']) // We'll handle show in the main product show page
            ->names([
                'index' => 'products.editions.index',
                'create' => 'products.editions.create',
                'store' => 'products.editions.store',
                'edit' => 'products.editions.edit',
                'update' => 'products.editions.update',
                'destroy' => 'products.editions.destroy',
            ]);

        Route::post('products/{product}/editions/bulk', [ProductEditionBulkController::class, 'store'])->name('products.editions.store-bulk');
        Route::get('products/{product}/editions/qr-batch-pdf', ProductEditionQrBatchPdfController::class)->name('products.editions.qr-batch-pdf');

        // Admin-only routes
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::resource('artists', ArtistController::class);
        });
    });
