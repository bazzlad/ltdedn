<?php

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin,artist'])
    ->group(function () {
        // Dashboard route - dispatches to appropriate controller based on role
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Admin-only routes
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', UserController::class);
            Route::resource('artists', ArtistController::class);
        });
    });
