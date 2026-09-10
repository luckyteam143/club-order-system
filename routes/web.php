<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BrochureController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubTeamController;
use App\Http\Controllers\LogoStockController;
use App\Http\Controllers\LogoTypeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ProductController;

// Route::redirect (not a closure) so `route:cache` can serialize it.
Route::redirect('/', '/admin');

Route::middleware(['auth'])->group(function () {
    Route::get('/orders/{order}/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}/pick-list', [OrderController::class, 'pickList'])->name('orders.pick-list');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('/clubs/export', [ClubController::class, 'export'])->name('clubs.export');
    Route::get('/clubs/{club}/items/export', [ClubController::class, 'exportItems'])->name('clubs.items.export');
    Route::get('/packages/{package}/items/export', [PackageController::class, 'exportItems'])->name('packages.items.export');
    Route::get('/packages/{package}/embellishments/export', [PackageController::class, 'exportEmbellishments'])->name('packages.embellishments.export');
    Route::get('/brochures/export', [BrochureController::class, 'export'])->name('brochures.export');
    Route::get('/logo-types/export', [LogoTypeController::class, 'export'])->name('logo-types.export');
    Route::get('/logo-stock/export', [LogoStockController::class, 'export'])->name('logo-stock.export');
    Route::get('/club-teams/export', [ClubTeamController::class, 'export'])->name('club-teams.export');
    Route::get('/media/export', [MediaController::class, 'export'])->name('media.export');
});
