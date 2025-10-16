<?php

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductEditionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController as MainDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,artist'])
    ->group(function () {
        // Dashboard route - dispatches to appropriate controller based on role
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Alternative dashboard with sidebar for admin users
        Route::get('/collection', MainDashboardController::class)->name('collection');

        // Shared routes (both admin and artist can access)
        Route::resource('products', ProductController::class);

        // Nested edition routes
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

        // Batch PDF of QRs for selected editions or all editions of a product
        Route::match(['GET', 'POST'], 'products/{product}/editions/qr-batch-pdf', [ProductEditionController::class, 'qrBatchPdf'])
            ->name('products.editions.qr-batch-pdf');

        // Admin-only routes
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::resource('artists', ArtistController::class);
        });
    });
