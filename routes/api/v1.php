<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

// Cart (works with session, no auth required)
Route::prefix('v1')->middleware(['web'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('api.v1.cart.show');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('api.v1.cart.add');
    Route::put('/cart/items/{item}', [CartController::class, 'updateItem'])->name('api.v1.cart.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'removeItem'])->name('api.v1.cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->name('api.v1.cart.clear');
});

// Reviews (public listing)
Route::prefix('v1')->group(function () {
    Route::get('/products/{product:slug}/reviews', [ReviewController::class, 'index'])->name('api.v1.products.reviews.index');
});

// Search (public: products and categories; authenticated: orders)
Route::prefix('v1')->group(function () {
    Route::get('/search/products', [SearchController::class, 'products'])->name('api.v1.search.products');
    Route::get('/search/categories', [SearchController::class, 'categories'])->name('api.v1.search.categories');
});

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/search/orders', [SearchController::class, 'orders'])->name('api.v1.search.orders');
});

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Reviews (authenticated)
    Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store'])->name('api.v1.products.reviews.store');
    // Checkout
    Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('api.v1.checkout');
    Route::post('/checkout/confirm-payment', [CheckoutController::class, 'confirmPayment'])->name('api.v1.checkout.confirm-payment');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('api.v1.orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('api.v1.orders.show');

    // Refunds
    Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->name('api.v1.orders.refunds.store');

    // Promotions
    Route::post('/cart/apply-promotion', [PromotionController::class, 'apply'])->name('api.v1.cart.apply-promotion');
    Route::post('/cart/validate-promotion', [PromotionController::class, 'validate'])->name('api.v1.cart.validate-promotion');
    Route::get('/promotions/active', [PromotionController::class, 'active'])->name('api.v1.promotions.active');

    // Loyalty
    Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('api.v1.loyalty.index');
    Route::get('/loyalty/transactions', [LoyaltyController::class, 'transactions'])->name('api.v1.loyalty.transactions');
    Route::post('/loyalty/redeem', [LoyaltyController::class, 'redeem'])->name('api.v1.loyalty.redeem');

    // Referral
    Route::prefix('referral')->group(function (): void {
        Route::get('/', [ReferralController::class, 'show'])->name('api.v1.referral.show');
        Route::get('/stats', [ReferralController::class, 'stats'])->name('api.v1.referral.stats');
        Route::post('/apply', [ReferralController::class, 'apply'])->name('api.v1.referral.apply');
        Route::post('/regenerate', [ReferralController::class, 'regenerate'])->name('api.v1.referral.regenerate');
    });

    // Chat
    Route::get('/conversations', [ConversationController::class, 'index'])->name('api.v1.conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('api.v1.conversations.store');
    Route::get('/conversations/{conversationId}', [ConversationController::class, 'show'])->name('api.v1.conversations.show');
    Route::patch('/conversations/{conversationId}/close', [ConversationController::class, 'close'])->name('api.v1.conversations.close');
    Route::post('/conversations/{conversationId}/messages', [MessageController::class, 'store'])->name('api.v1.conversations.messages.store');
    Route::post('/conversations/{conversationId}/read', [MessageController::class, 'markRead'])->name('api.v1.conversations.read');
});
