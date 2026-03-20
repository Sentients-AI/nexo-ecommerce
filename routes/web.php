<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CartController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ReferralWebController;
use App\Http\Controllers\Web\RefundController;
use App\Http\Controllers\Web\SocialiteController;
use App\Http\Controllers\Web\StoreController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Root redirects to default locale
Route::get('/', fn () => redirect('/en'));

// Referral links (global, no locale prefix needed)
Route::get('/r/{code}', [ReferralWebController::class, 'show'])->name('referral.use');

// Google OAuth (fixed URLs, outside locale prefix)
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [SocialiteController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

// Locale-prefixed routes
Route::prefix('{locale}')
    ->where(['locale' => 'en|ar|ms'])
    ->middleware('locale')
    ->group(function () {
        // Home page
        Route::get('/', fn () => Inertia::render('Home'))->name('home');

        // Products
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

        // Stores
        Route::get('/stores/{slug}', [StoreController::class, 'show'])->name('stores.show');

        // Cart
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

        // Wishlist
        Route::get('/wishlist', [ProductController::class, 'wishlist'])->name('wishlist.index');

        // Authentication
        Route::middleware('guest')->group(function () {
            Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
            Route::post('/login', [AuthController::class, 'login']);
            Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
            Route::post('/register', [AuthController::class, 'register']);
        });

        Route::middleware('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            // Checkout
            Route::get('/checkout', [CheckoutController::class, 'summary'])->name('checkout.summary');
            Route::get('/checkout/pending', [CheckoutController::class, 'pending'])->name('checkout.pending');
            Route::get('/checkout/result', [CheckoutController::class, 'result'])->name('checkout.result');

            // Orders
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

            // Refunds
            Route::get('/orders/{order}/refund', [RefundController::class, 'create'])->name('refunds.create');
            Route::get('/refunds/{refund}', [RefundController::class, 'show'])->name('refunds.show');
        });
    });
