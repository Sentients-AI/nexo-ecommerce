<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AddressController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\BundleController;
use App\Http\Controllers\Web\CartController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\DownloadController;
use App\Http\Controllers\Web\FlashSaleController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LoyaltyController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ReferralWebController;
use App\Http\Controllers\Web\RefundController;
use App\Http\Controllers\Web\ReturnRequestController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\SocialiteController;
use App\Http\Controllers\Web\StoreController;
use App\Http\Controllers\Web\VendorAnalyticsController;
use App\Http\Controllers\Web\VendorBundleController;
use App\Http\Controllers\Web\VendorCustomerController;
use App\Http\Controllers\Web\VendorDashboardController;
use App\Http\Controllers\Web\VendorEarningsController;
use App\Http\Controllers\Web\VendorInventoryController;
use App\Http\Controllers\Web\VendorOrderController;
use App\Http\Controllers\Web\VendorOrderExportController;
use App\Http\Controllers\Web\VendorProductController;
use App\Http\Controllers\Web\VendorProductImportController;
use App\Http\Controllers\Web\VendorPromotionController;
use App\Http\Controllers\Web\VendorQuestionController;
use App\Http\Controllers\Web\VendorReturnController;
use App\Http\Controllers\Web\VendorSettingsController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Stripe webhook — no auth/CSRF (signature verification is done in controller)
Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

// Secure file downloads — no auth required, token is the credential
Route::get('/downloads/{token}', [DownloadController::class, 'show'])->name('downloads.show');

// Sitemap — tenant-scoped, no auth required
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Root redirects to default locale
Route::get('/', fn () => redirect('/en'));

// Tenant self-service onboarding (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/start', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/start', [OnboardingController::class, 'store'])->name('onboarding.store');
});

// Vendor dashboard (authenticated, no locale prefix for clean URLs)
Route::prefix('vendor')
    ->name('vendor.')
    ->middleware(['auth', 'tenant.user'])
    ->group(function () {
        Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/orders', [VendorOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/export', VendorOrderExportController::class)->name('orders.export');
        Route::patch('/orders/{order}/status', [VendorOrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('/orders/{order}/ship', [VendorOrderController::class, 'shipOrder'])->name('orders.ship');
        Route::get('/products', [VendorProductController::class, 'index'])->name('products.index');
        Route::post('/products/bulk-action', [VendorProductController::class, 'bulkAction'])->name('products.bulk-action');
        Route::get('/products/create', [VendorProductController::class, 'create'])->name('products.create');
        Route::post('/products', [VendorProductController::class, 'store'])->name('products.store');
        Route::get('/products/import', [VendorProductImportController::class, 'create'])->name('products.import');
        Route::post('/products/import', [VendorProductImportController::class, 'store'])->name('products.import.store');
        Route::get('/products/{product}/edit', [VendorProductController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [VendorProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [VendorProductController::class, 'destroy'])->name('products.destroy');
        Route::get('/inventory', [VendorInventoryController::class, 'index'])->name('inventory.index');
        Route::patch('/inventory/{stock}', [VendorInventoryController::class, 'update'])->name('inventory.update');
        Route::get('/customers', [VendorCustomerController::class, 'index'])->name('customers.index');
        Route::get('/analytics', [VendorAnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/earnings', [VendorEarningsController::class, 'index'])->name('earnings.index');
        Route::get('/returns', [VendorReturnController::class, 'index'])->name('returns.index');
        Route::patch('/returns/{return}/approve', [VendorReturnController::class, 'approve'])->name('returns.approve');
        Route::patch('/returns/{return}/reject', [VendorReturnController::class, 'reject'])->name('returns.reject');
        Route::get('/questions', [VendorQuestionController::class, 'index'])->name('questions.index');
        Route::post('/questions/{question}/answer', [VendorQuestionController::class, 'answer'])->name('questions.answer');
        Route::get('/promotions', [VendorPromotionController::class, 'index'])->name('promotions.index');
        Route::patch('/promotions/{promotion}/toggle', [VendorPromotionController::class, 'toggle'])->name('promotions.toggle');
        Route::get('/settings', [VendorSettingsController::class, 'index'])->name('settings.index');

        // Bundles
        Route::get('/bundles', [VendorBundleController::class, 'index'])->name('bundles.index');
        Route::get('/bundles/create', [VendorBundleController::class, 'create'])->name('bundles.create');
        Route::post('/bundles', [VendorBundleController::class, 'store'])->name('bundles.store');
        Route::get('/bundles/{bundle}/edit', [VendorBundleController::class, 'edit'])->name('bundles.edit');
        Route::patch('/bundles/{bundle}', [VendorBundleController::class, 'update'])->name('bundles.update');
        Route::delete('/bundles/{bundle}', [VendorBundleController::class, 'destroy'])->name('bundles.destroy');
    });

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
        Route::get('/', HomeController::class)->name('home');

        // Products
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

        // Bundles
        Route::get('/bundles', [BundleController::class, 'index'])->name('bundles.index');
        Route::get('/bundles/{slug}', [BundleController::class, 'show'])->name('bundles.show');

        // Stores
        Route::get('/stores/{slug}', [StoreController::class, 'show'])->name('stores.show');

        // Cart
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

        // Flash sales
        Route::get('/flash-sales', [FlashSaleController::class, 'index'])->name('flash-sales.index');

        // Wishlist
        Route::get('/wishlist', [ProductController::class, 'wishlist'])->name('wishlist.index');

        // Authentication
        Route::middleware('guest')->group(function () {
            Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
            Route::post('/login', [AuthController::class, 'login']);
            Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
            Route::post('/register', [AuthController::class, 'register']);
        });

        // Checkout — accessible to both guests and authenticated users
        Route::get('/checkout', [CheckoutController::class, 'summary'])->name('checkout.summary');
        Route::get('/checkout/pending', [CheckoutController::class, 'pending'])->name('checkout.pending');
        Route::get('/checkout/result', [CheckoutController::class, 'result'])->name('checkout.result');

        Route::middleware('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            // Addresses
            Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
            Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::patch('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
            Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
            Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('addresses.set-default');

            // Orders
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{orderId}', [OrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{orderId}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
            Route::post('/orders/{orderId}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');

            // Refunds
            Route::get('/orders/{orderId}/refund', [RefundController::class, 'create'])->name('refunds.create');
            Route::get('/refunds/{refund}', [RefundController::class, 'show'])->name('refunds.show');

            // Return requests
            Route::get('/orders/{orderId}/return', [ReturnRequestController::class, 'create'])->name('returns.create');
            Route::post('/orders/{orderId}/return', [ReturnRequestController::class, 'store'])->name('returns.store');

            // Referrals
            Route::get('/referrals', [ReferralWebController::class, 'index'])->name('referrals.index');

            // Loyalty
            Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');

            // Subscriptions / Billing
            Route::get('/subscriptions', [BillingController::class, 'index'])->name('subscriptions.index');
            Route::post('/subscriptions/{plan}/checkout', [BillingController::class, 'checkout'])->name('subscriptions.checkout');
            Route::post('/subscriptions/cancel', [BillingController::class, 'cancel'])->name('subscriptions.cancel');
            Route::get('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

            // Notifications
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
            Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
            Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        });
    });
