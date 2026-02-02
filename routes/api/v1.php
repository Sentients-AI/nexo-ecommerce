<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\RefundController;
use Illuminate\Support\Facades\Route;

// Cart (works with session, no auth required)
Route::prefix('v1')->middleware(['web'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('api.v1.cart.show');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('api.v1.cart.add');
    Route::put('/cart/items/{item}', [CartController::class, 'updateItem'])->name('api.v1.cart.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'removeItem'])->name('api.v1.cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->name('api.v1.cart.clear');
});

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Checkout
    Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('api.v1.checkout');
    Route::post('/checkout/confirm-payment', [CheckoutController::class, 'confirmPayment'])->name('api.v1.checkout.confirm-payment');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('api.v1.orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('api.v1.orders.show');

    // Refunds
    Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->name('api.v1.orders.refunds.store');
});
