<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BrochureController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubTeamController;
use App\Http\Controllers\LogoStockController;
use App\Http\Controllers\LogoTypeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;

Route::get('/', fn () => redirect('/admin'));

Route::middleware(['auth'])->group(function () {
    Route::get('/orders/{order}/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('/clubs/export', [ClubController::class, 'export'])->name('clubs.export');
    Route::get('/brochures/export', [BrochureController::class, 'export'])->name('brochures.export');
    Route::get('/stock/export', [StockController::class, 'export'])->name('stock.export');
    Route::get('/logo-types/export', [LogoTypeController::class, 'export'])->name('logo-types.export');
    Route::get('/logo-stock/export', [LogoStockController::class, 'export'])->name('logo-stock.export');
    Route::get('/club-teams/export', [ClubTeamController::class, 'export'])->name('club-teams.export');
});
