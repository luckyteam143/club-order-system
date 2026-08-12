<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;

Route::get('/', fn () => redirect('/admin'));

Route::middleware(['auth'])->group(function () {
    Route::get('/orders/{order}/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('/stock/export', [StockController::class, 'export'])->name('stock.export');
});
