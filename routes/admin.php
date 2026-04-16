<?php

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductEditionBulkController;
use App\Http\Controllers\Admin\ProductEditionController;
use App\Http\Controllers\Admin\ProductEditionQrBatchPdfController;
use App\Http\Controllers\Admin\ProductSkuController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\SalesController;
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

        Route::get('products/{product}/skus', [ProductSkuController::class, 'index'])->name('products.skus.index');
        Route::post('products/{product}/skus', [ProductSkuController::class, 'store'])->name('products.skus.store');
        Route::put('products/{product}/skus/{sku}', [ProductSkuController::class, 'update'])->name('products.skus.update');
        Route::delete('products/{product}/skus/{sku}', [ProductSkuController::class, 'destroy'])->name('products.skus.destroy');

        Route::post('products/{product}/variants/axes', [ProductVariantController::class, 'syncAxes'])->name('products.variants.axes');
        Route::post('products/{product}/variants/regenerate', [ProductVariantController::class, 'regenerateSkus'])->name('products.variants.regenerate');

        // Admin-only routes
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::resource('artists', ArtistController::class);
            Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
            Route::get('sales/export/csv', [SalesController::class, 'exportCsv'])->name('sales.export.csv');
            Route::get('sales/{order}', [SalesController::class, 'show'])->name('sales.show');
            Route::post('sales/{order}/ship', [SalesController::class, 'markShipped'])->name('sales.ship');
            Route::post('sales/{order}/refund', [SalesController::class, 'refund'])->name('sales.refund');
        });
    });
