<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BundleController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\GiftCardController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ShippingMethodController;
use App\Http\Controllers\Api\V1\WaitlistController;
use Illuminate\Support\Facades\Route;

// Cart (works with session, no auth required)
Route::prefix('v1')->middleware(['web'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('api.v1.cart.show');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('api.v1.cart.add');
    Route::put('/cart/items/{item}', [CartController::class, 'updateItem'])->name('api.v1.cart.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'removeItem'])->name('api.v1.cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->name('api.v1.cart.clear');
});

// Shipping methods (public — available per tenant context)
Route::prefix('v1')->group(function () {
    Route::get('/shipping-methods', [ShippingMethodController::class, 'index'])->name('api.v1.shipping-methods.index');
});

// Bundles (public listing; add-to-cart uses web session)
Route::prefix('v1')->group(function () {
    Route::get('/bundles', [BundleController::class, 'index'])->name('api.v1.bundles.index');
    Route::get('/bundles/{slug}', [BundleController::class, 'show'])->name('api.v1.bundles.show');
});

Route::prefix('v1')->middleware(['web'])->group(function () {
    Route::post('/bundles/{slug}/cart', [BundleController::class, 'addToCart'])->name('api.v1.bundles.cart');
});

// Promotion preview — public, works for guests and authenticated users
Route::prefix('v1')->middleware(['web'])->group(function () {
    Route::post('/promotions/preview', [PromotionController::class, 'preview'])->name('api.v1.promotions.preview');
});

// Gift card preview — public, works for guests and authenticated users
Route::prefix('v1')->middleware(['web'])->group(function () {
    Route::post('/gift-cards/preview', [GiftCardController::class, 'preview'])->name('api.v1.gift-cards.preview');
});

// Reviews (public listing)
Route::prefix('v1')->group(function () {
    Route::get('/products/{product:slug}/reviews', [ReviewController::class, 'index'])->name('api.v1.products.reviews.index');
});

// Questions (public listing)
Route::prefix('v1')->group(function () {
    Route::get('/products/{product:slug}/questions', [QuestionController::class, 'index'])->name('api.v1.products.questions.index');
});

// Waitlist (public — no auth required)
Route::prefix('v1')->group(function () {
    Route::post('/products/{product:slug}/waitlist', [WaitlistController::class, 'store'])->name('api.v1.products.waitlist.store');
});

// Checkout — no auth required (supports both authenticated and guest users)
Route::prefix('v1')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('api.v1.checkout');
});

// Confirm payment — requires authentication
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/checkout/confirm-payment', [CheckoutController::class, 'confirmPayment'])->name('api.v1.checkout.confirm-payment');
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
    Route::post('/reviews/{review}/replies', [ReviewController::class, 'storeReply'])->name('api.v1.reviews.replies.store');
    Route::post('/reviews/{review}/vote', [ReviewController::class, 'vote'])->name('api.v1.reviews.vote');

    // Questions (authenticated)
    Route::post('/products/{product:slug}/questions', [QuestionController::class, 'store'])->name('api.v1.products.questions.store');
    Route::post('/questions/{question}/answers', [QuestionController::class, 'storeAnswer'])->name('api.v1.questions.answers.store');
    // Checkout (auth:sanctum removed; guest checkout handled via optional auth)

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

    // Notifications
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('api.v1.notifications.recent');

    // Chat
    Route::get('/conversations', [ConversationController::class, 'index'])->name('api.v1.conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('api.v1.conversations.store');
    Route::get('/conversations/{conversationId}', [ConversationController::class, 'show'])->name('api.v1.conversations.show');
    Route::patch('/conversations/{conversationId}/close', [ConversationController::class, 'close'])->name('api.v1.conversations.close');
    Route::post('/conversations/{conversationId}/messages', [MessageController::class, 'store'])->name('api.v1.conversations.messages.store');
    Route::post('/conversations/{conversationId}/read', [MessageController::class, 'markRead'])->name('api.v1.conversations.read');
});
