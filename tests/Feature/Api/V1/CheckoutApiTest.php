<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\DTOs\ProviderResponse;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    // Mock the payment gateway service for all checkout tests
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
            reference: $intent->provider_reference,
            clientSecret: 'cs_test_confirmed',
        ));
    $this->app->instance(PaymentGatewayService::class, $mock);
});

describe('Checkout API', function () {
    it('requires guest_email when unauthenticated', function () {
        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => 1,
            'currency' => 'USD',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['guest_email']);
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['cart_id', 'currency']);
    });

    it('rejects checkout for cart not owned by user', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $cart = Cart::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    });

    it('rejects checkout with empty cart', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'CART_EMPTY');
    });

    it('creates order from cart with items', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 2,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'order' => [
                'id',
                'order_number',
                'status',
                'subtotal_cents',
                'tax_cents',
                'total_cents',
                'currency',
            ],
            'payment_intent' => [
                'id',
                'status',
                'amount',
                'currency',
            ],
        ]);
    });

    it('returns cached response for duplicate idempotency key', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($user);
        $idempotencyKey = 'test-idempotency-key-123';

        $response1 = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ], ['Idempotency-Key' => $idempotencyKey]);

        $response1->assertSuccessful();
        $orderId1 = $response1->json('order.id');

        // Add more items to cart (it was cleared after checkout)
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
        ]);

        // Same idempotency key should return cached response (even with different cart state)
        $response2 = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ], ['Idempotency-Key' => $idempotencyKey]);

        $response2->assertSuccessful();
        expect($response2->json('order.id'))->toBe($orderId1);
    });

    it('applies loyalty points discount at checkout', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $baseCurrency = $this->tenant->settings['currency'];

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => $baseCurrency,
            'redeem_points' => 200,
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('order.loyalty_discount_cents', 200); // 200 points * 1 cent each (no conversion when using base currency)

        // Verify points were deducted
        expect(LoyaltyAccount::query()->where('user_id', $user->id)->first()->points_balance)->toBe(300);
    });

    it('rejects checkout when loyalty points are insufficient', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        LoyaltyAccount::factory()->withPoints(100)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
            'redeem_points' => 500,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'INSUFFICIENT_POINTS');
    });

    it('rejects checkout when user has no loyalty account but requests point redemption', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
            'redeem_points' => 100,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'INSUFFICIENT_POINTS');
    });

    it('reserves stock for the correct variant row when cart item has a variant', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $variantA = ProductVariant::factory()->forProduct($product)->create();
        $variantB = ProductVariant::factory()->forProduct($product)->create();

        // Two separate stock rows — one per variant
        $stockA = Stock::factory()->create([
            'product_id' => $product->id,
            'variant_id' => $variantA->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);
        $stockB = Stock::factory()->create([
            'product_id' => $product->id,
            'variant_id' => $variantB->id,
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variantA->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 3,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'MYR',
        ]);

        $response->assertSuccessful();

        // Only variant A's stock should be decremented
        expect($stockA->fresh()->quantity_available)->toBe(7)
            ->and($stockB->fresh()->quantity_available)->toBe(5);

        // Order item should carry the variant_id
        $orderId = $response->json('order.id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'variant_id' => $variantA->id,
            'quantity' => 3,
        ]);
    });

    it('rejects checkout with insufficient stock', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 10,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    });

    it('allows guest checkout with valid guest_email', function () {
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => null]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
            'quantity' => 1,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
            'guest_email' => 'guest@example.com',
            'guest_name' => 'Guest User',
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'order' => ['id', 'order_number', 'status'],
            'payment_intent' => ['id', 'status'],
        ]);

        $orderId = $response->json('order.id');
        $order = Order::query()->find($orderId);
        expect($order->user_id)->toBeNull()
            ->and($order->guest_email)->toBe('guest@example.com')
            ->and($order->guest_name)->toBe('Guest User')
            ->and($order->guest_token)->not->toBeNull();
    });

    it('persists guest_token on the order after guest checkout', function () {
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => null]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 3000,
            'tax_cents_snapshot' => 300,
            'quantity' => 1,
        ]);

        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
            'guest_email' => 'guest2@example.com',
        ]);

        $response->assertSuccessful();
        $orderId = $response->json('order.id');
        $order = Order::query()->find($orderId);
        expect($order->guest_token)->not->toBeNull();
    });

    it('rejects guest checkout without guest_email', function () {
        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => 1,
            'currency' => 'USD',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['guest_email']);
    });
});

describe('Confirm Payment API', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/checkout/confirm-payment', [
            'payment_intent_id' => 1,
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout/confirm-payment', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['payment_intent_id']);
    });

    it('rejects confirming payment for order not owned by user', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::factory()->pending()->create(['user_id' => $owner->id]);
        $paymentIntent = PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'status' => 'requires_payment',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/v1/checkout/confirm-payment', [
            'payment_intent_id' => $paymentIntent->id,
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    });

    it('confirms payment successfully', function () {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        $paymentIntent = PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'status' => 'processing',  // Must be processing to be confirmed
            'provider_reference' => 'pi_test_123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout/confirm-payment', [
            'payment_intent_id' => $paymentIntent->id,
        ]);

        // Should return the confirmed payment intent and order
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'payment_intent' => ['id', 'status'],
            'order' => ['id', 'status'],
        ]);
        $response->assertJsonPath('payment_intent.status', 'succeeded');
    });
});
