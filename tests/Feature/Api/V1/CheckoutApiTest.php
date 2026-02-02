<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\DTOs\ProviderResponse;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Product\Models\Product;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Mock the payment gateway service for all checkout tests
    $mock = Mockery::mock(PaymentGatewayService::class);
    $mock->shouldReceive('createIntent')
        ->andReturnUsing(function ($intent) {
            return new ProviderResponse(
                provider: 'test',
                reference: 'pi_test_'.uniqid(),
                clientSecret: 'cs_test_'.uniqid(),
            );
        });
    $mock->shouldReceive('confirmIntent')
        ->andReturnUsing(function ($intent) {
            return new ProviderResponse(
                provider: 'test',
                reference: $intent->provider_reference,
                clientSecret: 'cs_test_confirmed',
            );
        });
    $this->app->instance(PaymentGatewayService::class, $mock);
});

describe('Checkout API', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => 1,
            'currency' => 'USD',
        ]);

        $response->assertUnauthorized();
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
