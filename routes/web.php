<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;

Route::get('/', fn () => redirect('/admin'));

Route::middleware(['auth'])->group(function () {
    Route::get('/orders/{order}/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
});
