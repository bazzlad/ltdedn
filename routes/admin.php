<?php

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExternalImportController;
use App\Http\Controllers\Admin\FulfilmentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductEditionBulkController;
use App\Http\Controllers\Admin\ProductEditionController;
use App\Http\Controllers\Admin\ProductEditionCsvController;
use App\Http\Controllers\Admin\ProductEditionQrBatchPdfController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\StorefrontConnectionController;
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
        Route::get('products/{product}/editions/csv', ProductEditionCsvController::class)->name('products.editions.csv');
        Route::get('products/{product}/editions/qr-batch-pdf', ProductEditionQrBatchPdfController::class)->name('products.editions.qr-batch-pdf');

        // Admin-only routes
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::resource('artists', ArtistController::class);
            Route::get('fulfilment', [FulfilmentController::class, 'index'])->name('fulfilment.index');
            Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
            Route::get('sales/{order}', [SalesController::class, 'show'])->name('sales.show');
            Route::post('sales/{order}/ship', [SalesController::class, 'markShipped'])->name('sales.ship');
            Route::get('external-imports', [ExternalImportController::class, 'index'])->name('external-imports.index');
            Route::get('storefront-connections', [StorefrontConnectionController::class, 'index'])->name('storefront-connections.index');
            Route::get('storefront-connections/create', [StorefrontConnectionController::class, 'create'])->name('storefront-connections.create');
            Route::post('storefront-connections', [StorefrontConnectionController::class, 'store'])->name('storefront-connections.store');
            Route::get('storefront-connections/{connection}', [StorefrontConnectionController::class, 'show'])->name('storefront-connections.show');
            Route::post('storefront-connections/{connection}/test', [StorefrontConnectionController::class, 'test'])->name('storefront-connections.test');
            Route::post('storefront-connections/{connection}/activate', [StorefrontConnectionController::class, 'activate'])->name('storefront-connections.activate');
        });
    });
