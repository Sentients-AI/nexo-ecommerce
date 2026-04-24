<?php

declare(strict_types=1);

/**
 * End-to-End Customer Journey Tests
 *
 * Covers the full lifecycle of a customer interacting with the storefront:
 * authentication → product discovery → cart → checkout → orders → profile features.
 */

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Category\Models\Category;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\DTOs\ProviderResponse;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Review\Models\Review;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/**
 * Mock the payment gateway so no real Stripe calls are made.
 */
function mockPaymentGateway(): void
{
    $mock = Mockery::mock(PaymentGatewayService::class);

    $mock->shouldReceive('createIntent')
        ->andReturnUsing(fn ($intent) => new ProviderResponse(
            provider: 'test',
            reference: 'pi_test_'.uniqid(),
            clientSecret: 'cs_test_'.uniqid(),
        ));

    $mock->shouldReceive('confirmIntent')
        ->andReturnUsing(fn ($intent) => new ProviderResponse(
            provider: 'test',
            reference: $intent->provider_reference ?? 'pi_test_confirmed',
            clientSecret: 'cs_test_confirmed',
        ));

    app()->instance(PaymentGatewayService::class, $mock);
}

/**
 * Create a DatabaseNotification record for a user.
 */
function createNotification(User $user): DatabaseNotification
{
    $notification = new OrderStatusChangedNotification(1, 'ORD-001', 'paid');

    return DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => OrderStatusChangedNotification::class,
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => $notification->toArray($user),
        'read_at' => null,
    ]);
}

// ---------------------------------------------------------------------------
// 1. AUTHENTICATION JOURNEY
// ---------------------------------------------------------------------------

describe('1. Authentication Journey', function () {
    beforeEach(function () {
        $this->seed(RoleSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    });

    it('step 1.1 — shows the login page to guests', function () {
        $this->get('/en/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    });

    it('step 1.2 — shows the registration page to guests', function () {
        $this->get('/en/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    });

    it('step 1.3 — can register a new customer account', function () {
        $this->setUpTenant(['is_active' => true]);

        $response = $this->post('/en/register', [
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();
        $this->assertDatabaseHas(User::class, ['email' => 'alice@example.com']);
    });

    it('step 1.4 — registration fails with invalid data', function () {
        $this->setUpTenant(['is_active' => true]);

        $this->post('/en/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors(['name', 'email', 'password']);
    });

    it('step 1.5 — can log in with valid credentials', function () {
        $this->setUpTenant(['is_active' => true]);
        $user = User::factory()->forTenant($this->tenant)->create([
            'password' => Hash::make('Password123!'),
        ]);

        $this->post('/en/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    });

    it('step 1.6 — login fails with wrong password', function () {
        $this->setUpTenant(['is_active' => true]);
        User::factory()->forTenant($this->tenant)->create([
            'email' => 'bob@example.com',
            'password' => Hash::make('RealPassword123!'),
        ]);

        $this->post('/en/login', [
            'email' => 'bob@example.com',
            'password' => 'WrongPassword!',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    });

    it('step 1.7 — authenticated user can log out', function () {
        $this->setUpTenant(['is_active' => true]);
        $user = $this->actingAsUserInTenant();

        $this->post('/en/logout')->assertRedirect();

        $this->assertGuest();
    });

    it('step 1.8 — authenticated users are redirected away from login', function () {
        $this->setUpTenant(['is_active' => true]);
        $this->actingAsUserInTenant();

        $this->get('/en/login')->assertRedirect();
    });

    it('step 1.9 — onboarding page is accessible to guests', function () {
        $this->get('/start')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Onboarding/Create'));
    });

    it('step 1.10 — vendor can self-onboard through onboarding form', function () {
        $response = $this->post('/start', [
            'store_name' => 'My New Shop',
            'store_slug' => 'my-new-shop',
            'store_email' => 'shop@example.com',
            'name' => 'Shop Owner',
            'email' => 'owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas(Tenant::class, ['slug' => 'my-new-shop']);
        $this->assertDatabaseHas(User::class, ['email' => 'owner@example.com']);
    });
});

// ---------------------------------------------------------------------------
// 2. PRODUCT DISCOVERY JOURNEY
// ---------------------------------------------------------------------------

describe('2. Product Discovery Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
    });

    it('step 2.1 — can browse the home page', function () {
        $this->get('/en/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    });

    it('step 2.2 — can browse the products listing', function () {
        Product::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->get('/en/products')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Index')
                ->has('products.data', 5)
            );
    });

    it('step 2.3 — can filter products by category', function () {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherCategory = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        Product::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        // Products in a different category should not appear
        Product::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'category_id' => $otherCategory->id,
        ]);

        $this->get("/en/products?category={$category->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Index')
                ->has('products.data', 3)
            );
    });

    it('step 2.4 — can view a single product detail page', function () {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Show')
                ->where('product.id', $product->id)
                ->where('product.slug', $product->slug)
            );
    });

    it('step 2.5 — inactive products return 404', function () {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $this->get("/en/products/{$product->slug}")->assertNotFound();
    });

    it('step 2.6 — product page increments view count on first visit', function () {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'view_count' => 0,
        ]);

        $this->get("/en/products/{$product->slug}")->assertOk();

        expect($product->fresh()->view_count)->toBe(1);
    });

    it('step 2.7 — can view a store page with its products', function () {
        Product::factory()->count(4)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->get("/en/stores/{$this->tenant->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stores/Show')
                ->has('products.data', 4)
            );
    });

    it('step 2.8 — can view the wishlist page', function () {
        $products = Product::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $ids = $products->pluck('id')->implode(',');

        $this->get("/en/wishlist?ids={$ids}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Wishlist/Index')
                ->has('products', 2)
            );
    });

    it('step 2.9 — can view the flash sales page', function () {
        $this->get('/en/flash-sales')
            ->assertOk();
    });

    it('step 2.10 — can view the bundles listing', function () {
        $this->get('/en/bundles')
            ->assertOk();
    });

    it('step 2.11 — can track an order publicly using order number and email', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
        ]);

        $this->get('/en/track')
            ->assertOk();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/en/track', [
                'order_number' => $order->order_number,
                'email' => $user->email,
            ])->assertOk();
    });
});

// ---------------------------------------------------------------------------
// 3. CART MANAGEMENT JOURNEY
// ---------------------------------------------------------------------------

describe('3. Cart Management Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'price_cents' => 2999,
        ]);
        Stock::factory()->create([
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'quantity_available' => 20,
            'quantity_reserved' => 0,
        ]);
    });

    it('step 3.1 — can view an empty cart', function () {
        $this->get('/en/cart')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Cart/Index'));
    });

    it('step 3.2 — can add a product to the cart via API', function () {
        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure(['cart' => ['items']]);

        expect($response->json('cart.items'))->not->toBeEmpty();
    });

    it('step 3.3 — can view the cart with items', function () {
        $user = $this->actingAsUserInTenant();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->get('/en/cart')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Cart/Index'));
    });

    it('step 3.4 — can update a cart item quantity', function () {
        // Use an authenticated user so the cart is identified by user_id (not session)
        $user = $this->actingAsUserInTenant();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response->assertSuccessful();
        $itemId = $response->json('cart.items.0.id');

        $updateResponse = $this->putJson("/api/v1/cart/items/{$itemId}", [
            'quantity' => 3,
        ]);

        $updateResponse->assertSuccessful();
        expect($updateResponse->json('cart.items.0.quantity'))->toBe(3);
    });

    it('step 3.5 — can remove an item from the cart', function () {
        $user = $this->actingAsUserInTenant();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response->assertSuccessful();
        $itemId = $response->json('cart.items.0.id');

        $this->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertSuccessful();

        $cartResponse = $this->getJson('/api/v1/cart');
        expect($cartResponse->json('cart.items'))->toBeEmpty();
    });

    it('step 3.6 — can clear the entire cart', function () {
        $this->actingAsUserInTenant();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ])->assertSuccessful();

        $this->deleteJson('/api/v1/cart')->assertSuccessful();

        $cartResponse = $this->getJson('/api/v1/cart');
        expect($cartResponse->json('cart.items'))->toBeEmpty();
    });

    it('step 3.7 — cannot add more items than available stock', function () {
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 999,
        ])->assertUnprocessable();
    });

    it('step 3.8 — can apply a promotion code to the cart', function () {
        $user = $this->actingAsUserInTenant();
        $cart = Cart::factory()->create(['user_id' => $user->id, 'tenant_id' => $this->tenant->id]);
        $cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 2999,
            'tax_cents_snapshot' => 300,
            'quantity' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        Promotion::factory()->fixed(500)->create([
            'code' => 'SAVE5',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->postJson('/api/v1/cart/apply-promotion', [
            'code' => 'SAVE5',
            'cart_id' => $cart->id,
        ])->assertSuccessful()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('discount_cents', 500);
    });

    it('step 3.9 — invalid promotion code returns an error', function () {
        $user = $this->actingAsUserInTenant();
        $cart = Cart::factory()->create(['user_id' => $user->id, 'tenant_id' => $this->tenant->id]);

        $this->postJson('/api/v1/cart/apply-promotion', [
            'code' => 'INVALID',
            'cart_id' => $cart->id,
        ])->assertUnprocessable();
    });
});

// ---------------------------------------------------------------------------
// 4. CHECKOUT & PAYMENT JOURNEY
// ---------------------------------------------------------------------------

describe('4. Checkout & Payment Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        mockPaymentGateway();

        $this->user = User::factory()->forTenant($this->tenant)->create();
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'price_cents' => 5000,
        ]);
        Stock::factory()->create([
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);
        $this->cart = Cart::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
            'tenant_id' => $this->tenant->id,
        ]);
    });

    it('step 4.1 — authenticated user can view checkout summary', function () {
        $this->actingAs($this->user)
            ->get('/en/checkout')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Summary')
                ->has('cart.items', 1)
            );
    });

    it('step 4.2 — empty cart redirects to cart page from checkout', function () {
        $emptyCart = Cart::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Remove items from cart before testing
        $this->cart->items()->delete();

        $this->actingAs($this->user)
            ->get('/en/checkout')
            ->assertRedirect('/en/cart');
    });

    it('step 4.3 — can complete checkout via API and receive order + payment intent', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $this->cart->id,
            'currency' => 'USD',
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'order' => ['id', 'order_number', 'status', 'subtotal_cents', 'total_cents', 'currency'],
                'payment_intent' => ['id', 'status', 'amount', 'currency'],
            ]);

        $this->assertDatabaseHas(Order::class, [
            'id' => $response->json('order.id'),
            'user_id' => $this->user->id,
        ]);
    });

    it('step 4.4 — checkout fails with insufficient stock', function () {
        Sanctum::actingAs($this->user);

        // Reserve all stock
        Stock::query()
            ->where('product_id', $this->product->id)
            ->update(['quantity_available' => 0]);

        $this->postJson('/api/v1/checkout', [
            'cart_id' => $this->cart->id,
            'currency' => 'USD',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    });

    it('step 4.5 — guest can checkout without an account', function () {
        $guestCart = Cart::factory()->create(['user_id' => null, 'tenant_id' => $this->tenant->id]);
        $guestCart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $guestCart->id,
            'currency' => 'USD',
            'guest_email' => 'guest@example.com',
            'guest_name' => 'Guest User',
        ]);

        $response->assertSuccessful();

        $order = Order::query()->find($response->json('order.id'));
        expect($order->user_id)->toBeNull()
            ->and($order->guest_email)->toBe('guest@example.com')
            ->and($order->guest_token)->not->toBeNull();
    });

    it('step 4.6 — can confirm a payment after checkout', function () {
        // First place an order
        Sanctum::actingAs($this->user);

        $checkoutResponse = $this->postJson('/api/v1/checkout', [
            'cart_id' => $this->cart->id,
            'currency' => 'USD',
        ]);

        $checkoutResponse->assertSuccessful();
        $paymentIntentId = $checkoutResponse->json('payment_intent.id');

        // Set intent to processing so it can be confirmed
        PaymentIntent::query()->where('id', $paymentIntentId)->update([
            'status' => 'processing',
            'provider_reference' => 'pi_test_123',
        ]);

        $confirmResponse = $this->postJson('/api/v1/checkout/confirm-payment', [
            'payment_intent_id' => $paymentIntentId,
        ]);

        $confirmResponse->assertSuccessful()
            ->assertJsonPath('payment_intent.status', 'succeeded');
    });

    it('step 4.7 — can checkout using loyalty points as a discount', function () {
        LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);

        $currency = $this->tenant->settings['currency'];

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $this->cart->id,
            'currency' => $currency,
            'redeem_points' => 200,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('order.loyalty_discount_cents', 200);

        expect(LoyaltyAccount::query()->where('user_id', $this->user->id)->value('points_balance'))->toBe(300);
    });

    it('step 4.8 — checkout rejects duplicate requests via idempotency key', function () {
        Sanctum::actingAs($this->user);
        $idempotencyKey = 'journey-test-key-'.uniqid();

        $first = $this->postJson('/api/v1/checkout', [
            'cart_id' => $this->cart->id,
            'currency' => 'USD',
        ], ['Idempotency-Key' => $idempotencyKey]);

        $first->assertSuccessful();
        $firstOrderId = $first->json('order.id');

        // Add items again (cart was cleared after checkout)
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        $second = $this->postJson('/api/v1/checkout', [
            'cart_id' => $this->cart->id,
            'currency' => 'USD',
        ], ['Idempotency-Key' => $idempotencyKey]);

        $second->assertSuccessful();
        expect($second->json('order.id'))->toBe($firstOrderId);
    });
});

// ---------------------------------------------------------------------------
// 5. ORDER MANAGEMENT JOURNEY
// ---------------------------------------------------------------------------

describe('5. Order Management Journey', function () {
    beforeEach(function () {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAsUserInTenant();
    });

    it('step 5.1 — authenticated user can view their order list', function () {
        Order::factory()->count(3)->create([
            'user_id' => auth()->id(),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->get('/en/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Index')
                ->has('orders')
            );
    });

    it('step 5.2 — guests are redirected to login when accessing orders', function () {
        auth()->logout();

        $this->get('/en/orders')->assertRedirect();
    });

    it('step 5.3 — customer can view their own order detail', function () {
        $order = Order::factory()->create([
            'user_id' => auth()->id(),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->get("/en/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Show')
                ->where('order.id', $order->id)
            );
    });

    it('step 5.4 — customer cannot view another users order', function () {
        $otherOrder = Order::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->get("/en/orders/{$otherOrder->id}")->assertNotFound();
    });

    it('step 5.5 — customer can download PDF invoice for their order', function () {
        $order = Order::factory()->create([
            'user_id' => auth()->id(),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->get("/en/orders/{$order->id}/invoice");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    it('step 5.6 — customer can reorder a previous order', function () {
        $order = Order::factory()->create([
            'user_id' => auth()->id(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Use two distinct products so reorder adds 2 separate cart items
        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $productB = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'tenant_id' => $this->tenant->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->post("/en/orders/{$order->id}/reorder")
            ->assertRedirect('/en/cart');

        $cart = Cart::query()->where('user_id', auth()->id())->first();
        expect($cart)->not->toBeNull()
            ->and(CartItem::query()->where('cart_id', $cart->id)->count())->toBe(2);
    });

    it('step 5.7 — can list orders via API', function () {
        Order::factory()->count(2)->create([
            'user_id' => auth()->id(),
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs(auth()->user());

        $this->getJson('/api/v1/orders')
            ->assertSuccessful()
            ->assertJsonStructure(['data' => [['id', 'order_number', 'status']]]);
    });
});

// ---------------------------------------------------------------------------
// 6. ADDRESS MANAGEMENT JOURNEY
// ---------------------------------------------------------------------------

describe('6. Address Management Journey', function () {
    beforeEach(function () {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAsUserInTenant();
    });

    it('step 6.1 — customer can view their addresses page', function () {
        Address::factory()->for(auth()->user())->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->get('/en/addresses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Addresses')
                ->has('addresses', 2)
            );
    });

    it('step 6.2 — guests are redirected to login when accessing addresses', function () {
        auth()->logout();

        $this->get('/en/addresses')->assertRedirect();
    });

    it('step 6.3 — customer can add a new address', function () {
        $this->post('/en/addresses', [
            'name' => 'Home',
            'address_line_1' => '123 Main Street',
            'city' => 'Kuala Lumpur',
            'postal_code' => '50000',
            'country' => 'MY',
            'is_default' => true,
        ])->assertRedirect('/en/addresses');

        $this->assertDatabaseHas(Address::class, [
            'user_id' => auth()->id(),
            'city' => 'Kuala Lumpur',
        ]);
    });

    it('step 6.4 — address creation fails without required fields', function () {
        $this->post('/en/addresses', [])
            ->assertSessionHasErrors(['name', 'address_line_1', 'city', 'postal_code', 'country']);
    });

    it('step 6.5 — customer can update their own address', function () {
        $address = Address::factory()->for(auth()->user())->create([
            'tenant_id' => $this->tenant->id,
            'city' => 'Old City',
        ]);

        $this->patch("/en/addresses/{$address->id}", [
            'name' => 'Home',
            'address_line_1' => '99 New Road',
            'city' => 'New City',
            'postal_code' => '60000',
            'country' => 'MY',
        ])->assertRedirect('/en/addresses');

        expect($address->fresh()->city)->toBe('New City');
    });

    it('step 6.6 — customer cannot update another users address', function () {
        $address = Address::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->patch("/en/addresses/{$address->id}", [
            'name' => 'Hack',
            'address_line_1' => 'Evil St',
            'city' => 'Evil City',
            'postal_code' => '99999',
            'country' => 'MY',
        ])->assertForbidden();
    });

    it('step 6.7 — customer can set an address as default', function () {
        $address = Address::factory()->for(auth()->user())->create([
            'tenant_id' => $this->tenant->id,
            'is_default' => false,
        ]);

        $this->patch("/en/addresses/{$address->id}/default")
            ->assertRedirect('/en/addresses');

        expect($address->fresh()->is_default)->toBeTrue();
    });

    it('step 6.8 — customer can delete their own address', function () {
        $address = Address::factory()->for(auth()->user())->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->delete("/en/addresses/{$address->id}")
            ->assertRedirect('/en/addresses');

        expect(Address::query()->find($address->id))->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// 7. LOYALTY PROGRAM JOURNEY
// ---------------------------------------------------------------------------

describe('7. Loyalty Program Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
    });

    it('step 7.1 — authenticated user can view their loyalty account', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        LoyaltyAccount::factory()->withPoints(350)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/loyalty')
            ->assertSuccessful()
            ->assertJsonPath('loyalty_account.points_balance', 350)
            ->assertJsonStructure([
                'loyalty_account' => [
                    'id',
                    'points_balance',
                    'total_points_earned',
                    'total_points_redeemed',
                    'points_value_cents',
                ],
            ]);
    });

    it('step 7.2 — loyalty account is auto-created for new users', function () {
        $user = User::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/loyalty');

        $response->assertSuccessful();
        expect($response->json('loyalty_account.points_balance'))->toBe(0);
        expect(LoyaltyAccount::query()->where('user_id', $user->id)->exists())->toBeTrue();
    });

    it('step 7.3 — can view loyalty transaction history', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        LoyaltyAccount::factory()->withPoints(100)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/loyalty/transactions')
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    });

    it('step 7.4 — loyalty page is accessible from the storefront', function () {
        $this->actingAsUserInTenant();

        $this->get('/en/loyalty')->assertOk();
    });

    it('step 7.5 — unauthenticated access to loyalty API returns 401', function () {
        $this->getJson('/api/v1/loyalty')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// 8. REFERRAL PROGRAM JOURNEY
// ---------------------------------------------------------------------------

describe('8. Referral Program Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
    });

    it('step 8.1 — authenticated user can retrieve their referral code', function () {
        $user = User::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/referral')
            ->assertSuccessful()
            ->assertJsonStructure([
                'referral_code' => [
                    'code',
                    'shareable_url',
                    'status',
                    'is_active',
                ],
            ]);
    });

    it('step 8.2 — referral code is auto-created if none exists', function () {
        $user = User::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/referral')->assertSuccessful();

        expect(ReferralCode::query()->where('user_id', $user->id)->exists())->toBeTrue();
    });

    it('step 8.3 — user can view referral stats', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        ReferralCode::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/referral/stats')
            ->assertSuccessful()
            ->assertJsonStructure(['stats']);
    });

    it('step 8.4 — user can regenerate their referral code', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        $old = ReferralCode::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/referral/regenerate');

        $response->assertSuccessful();
        expect($response->json('referral_code.code'))->not->toBe($old->code);
    });

    it('step 8.5 — referrals page is accessible from the storefront', function () {
        $this->actingAsUserInTenant();

        $this->get('/en/referrals')->assertOk();
    });

    it('step 8.6 — referral link renders the landing page for a valid code', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        $referralCode = ReferralCode::factory()->create(['user_id' => $user->id]);

        // A valid active referral code renders an Inertia page (200), not a redirect
        $this->get("/r/{$referralCode->code}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Referral/Show'));
    });
});

// ---------------------------------------------------------------------------
// 9. REVIEW SYSTEM JOURNEY
// ---------------------------------------------------------------------------

describe('9. Review System Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    });

    it('step 9.1 — anyone can list approved reviews for a product', function () {
        $users = User::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        foreach ($users as $user) {
            Review::factory()->create([
                'product_id' => $this->product->id,
                'user_id' => $user->id,
                'tenant_id' => $this->tenant->id,
                'is_approved' => true,
            ]);
        }

        // One unapproved review — should not appear
        Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => User::factory()->create()->id,
            'tenant_id' => $this->tenant->id,
            'is_approved' => false,
        ]);

        $this->getJson("/api/v1/products/{$this->product->slug}/reviews")
            ->assertSuccessful()
            ->assertJsonCount(3, 'data');
    });

    it('step 9.2 — authenticated customer can submit a product review', function () {
        $user = User::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 5,
            'title' => 'Excellent product!',
            'body' => 'Really happy with this purchase. Highly recommend it.',
        ])->assertSuccessful()
            ->assertJsonStructure(['data' => ['id', 'rating', 'title']]);
    });

    it('step 9.3 — review submission requires authentication', function () {
        $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 4,
            'title' => 'Good',
            'body' => 'Nice product.',
        ])->assertUnauthorized();
    });

    it('step 9.4 — review fails with invalid rating', function () {
        $user = User::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 10,
            'title' => 'Great',
            'body' => 'Amazing.',
        ])->assertUnprocessable();
    });

    it('step 9.5 — customer can vote (helpful) on a review', function () {
        $reviewer = User::factory()->forTenant($this->tenant)->create();
        $voter = User::factory()->forTenant($this->tenant)->create();

        $review = Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $reviewer->id,
            'tenant_id' => $this->tenant->id,
            'is_approved' => true,
        ]);

        Sanctum::actingAs($voter);

        // The API expects `is_helpful` (boolean), not a string vote value
        $this->postJson("/api/v1/reviews/{$review->id}/vote", [
            'is_helpful' => true,
        ])->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 10. NOTIFICATIONS JOURNEY
// ---------------------------------------------------------------------------

describe('10. Notifications Journey', function () {
    beforeEach(function () {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAsUserInTenant();
    });

    it('step 10.1 — authenticated user can view the notifications page', function () {
        createNotification(auth()->user());

        $this->get('/en/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Notifications/Index'));
    });

    it('step 10.2 — user can fetch recent notifications via API', function () {
        createNotification(auth()->user());

        Sanctum::actingAs(auth()->user());

        // The API returns `items` (not `notifications`)
        $this->getJson('/api/v1/notifications/recent')
            ->assertSuccessful()
            ->assertJsonStructure(['items']);
    });

    it('step 10.3 — user can mark a notification as read', function () {
        $notification = createNotification(auth()->user());

        $this->patch("/en/notifications/{$notification->id}/read")
            ->assertRedirect();

        expect($notification->fresh()->read_at)->not->toBeNull();
    });

    it('step 10.4 — user can mark all notifications as read', function () {
        createNotification(auth()->user());
        createNotification(auth()->user());

        $this->patch('/en/notifications/read-all')->assertRedirect();

        $unread = auth()->user()
            ->notifications()
            ->whereNull('read_at')
            ->count();

        expect($unread)->toBe(0);
    });

    it('step 10.5 — user can delete a notification', function () {
        $notification = createNotification(auth()->user());

        $this->delete("/en/notifications/{$notification->id}")
            ->assertRedirect();

        expect(DatabaseNotification::query()->find($notification->id))->toBeNull();
    });

    it('step 10.6 — guests are redirected to login', function () {
        auth()->logout();

        $this->get('/en/notifications')->assertRedirect();
    });
});

// ---------------------------------------------------------------------------
// 11. VENDOR DASHBOARD JOURNEY
// ---------------------------------------------------------------------------

describe('11. Vendor Dashboard Journey', function () {
    beforeEach(function () {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAsUserInTenant();
    });

    it('step 11.1 — vendor can access their dashboard', function () {
        $this->get('/vendor/dashboard')->assertOk();
    });

    it('step 11.2 — guests are redirected to login from vendor dashboard', function () {
        auth()->logout();

        $this->get('/vendor/dashboard')->assertRedirect();
    });

    it('step 11.3 — vendor can view products list', function () {
        Product::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->get('/vendor/products')->assertOk();
    });

    it('step 11.4 — vendor can access the create product page', function () {
        Category::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->get('/vendor/products/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/Products/Create'));
    });

    it('step 11.5 — vendor can create a new product', function () {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post('/vendor/products', [
            'name' => 'Wireless Earbuds Pro',
            'sku' => 'WEP-001',
            'price_cents' => '79.99',
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => false,
        ])->assertRedirect('/vendor/products');

        $this->assertDatabaseHas(Product::class, [
            'sku' => 'WEP-001',
            'tenant_id' => $this->tenant->id,
        ]);
    });

    it('step 11.6 — product creation fails with missing required fields', function () {
        $this->post('/vendor/products', [])
            ->assertSessionHasErrors(['name', 'sku', 'price_cents']);
    });

    it('step 11.7 — vendor can update a product', function () {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->patch("/vendor/products/{$product->id}", [
            'name' => 'New Name',
            'sku' => $product->sku,
            'price_cents' => '25.00',
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => false,
        ])->assertRedirect('/vendor/products');

        expect($product->fresh()->name)->toBe('New Name');
    });

    it('step 11.8 — vendor can delete a product', function () {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->delete("/vendor/products/{$product->id}")
            ->assertRedirect('/vendor/products');

        expect(Product::query()->find($product->id))->toBeNull();
    });

    it('step 11.9 — vendor can view inventory', function () {
        $this->get('/vendor/inventory')->assertOk();
    });

    it('step 11.10 — vendor can view orders list', function () {
        Order::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->get('/vendor/orders')->assertOk();
    });

    it('step 11.11 — vendor can view customer list', function () {
        $this->get('/vendor/customers')->assertOk();
    });

    it('step 11.12 — vendor can view analytics', function () {
        $this->get('/vendor/analytics')->assertOk();
    });

    it('step 11.13 — vendor can view earnings', function () {
        $this->get('/vendor/earnings')->assertOk();
    });

    it('step 11.14 — vendor can view storefront settings', function () {
        $this->get('/vendor/storefront')->assertOk();
    });

    it('step 11.15 — vendor can view account settings', function () {
        $this->get('/vendor/settings')->assertOk();
    });

    it('step 11.16 — vendor can view reviews page', function () {
        $this->get('/vendor/reviews')->assertOk();
    });

    it('step 11.17 — vendor can view promotions page', function () {
        $this->get('/vendor/promotions')->assertOk();
    });

    it('step 11.18 — vendor can view bundles listing', function () {
        $this->get('/vendor/bundles')->assertOk();
    });

    it('step 11.19 — vendor can access the bundle creation page', function () {
        $this->get('/vendor/bundles/create')->assertOk();
    });

    it('step 11.20 — vendor can view returns management', function () {
        $this->get('/vendor/returns')->assertOk();
    });

    it('step 11.21 — vendor can view product questions', function () {
        $this->get('/vendor/questions')->assertOk();
    });
});

// ---------------------------------------------------------------------------
// 12. SEARCH JOURNEY
// ---------------------------------------------------------------------------

describe('12. Search Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
    });

    it('step 12.1 — can search for products', function () {
        $this->getJson('/api/v1/search/products?q=test')
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    });

    it('step 12.2 — can search for categories', function () {
        $this->getJson('/api/v1/search/categories?q=electronics')
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    });

    it('step 12.3 — authenticated user can search their own orders', function () {
        $user = User::factory()->forTenant($this->tenant)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/search/orders?q=ORD')
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    });

    it('step 12.4 — unauthenticated order search returns 401', function () {
        $this->getJson('/api/v1/search/orders?q=ORD')->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// 13. SITEMAP JOURNEY
// ---------------------------------------------------------------------------

describe('13. Sitemap Journey', function () {
    beforeEach(function () {
        $this->setUpTenant(['is_active' => true]);
    });

    it('step 13.1 — sitemap is publicly accessible', function () {
        $this->get('/sitemap.xml')->assertOk();
    });
});
