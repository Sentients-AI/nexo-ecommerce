<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\DTOs\ProviderResponse;
use App\Domain\Product\Models\Product;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();

    $mock = Mockery::mock(PaymentGatewayService::class);
    $mock->shouldReceive('createIntent')
        ->andReturnUsing(fn ($intent) => new ProviderResponse(
            provider: 'test',
            reference: 'pi_test_'.uniqid(),
            clientSecret: 'cs_test_'.uniqid(),
        ));
    $this->app->instance(PaymentGatewayService::class, $mock);
});

describe('Checkout with multi-currency', function () {
    it('stores the currency on the order', function () {
        Http::fake([
            '*/latest*' => Http::response(['base' => 'MYR', 'rates' => ['USD' => 0.22]]),
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 800,
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
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('order.currency', 'USD');

        $order = Order::latest()->first();
        expect($order->currency)->toBe('USD')
            ->and($order->base_currency)->toBe('MYR')
            ->and($order->exchange_rate)->toBeFloat()
            ->and($order->base_total_cents)->toBeGreaterThan(0);
    });

    it('stores base_total_cents and exchange_rate columns on order', function () {
        Http::fake([
            '*/latest*' => Http::response(['base' => 'MYR', 'rates' => ['USD' => 0.22]]),
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 800,
            'quantity' => 1,
        ]);
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ])->assertSuccessful();

        $order = Order::latest()->first();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'currency' => 'USD',
            'base_currency' => 'MYR',
        ]);
        expect($order->exchange_rate)->toBe(0.22)
            ->and($order->base_total_cents)->toBeGreaterThan(0);
    });

    it('uses exchange rate of 1 when checking out in same currency as tenant', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 400,
            'quantity' => 1,
        ]);
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($user);

        // MYR is the default tenant currency; no API call needed
        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'MYR',
        ]);

        $response->assertSuccessful();

        $order = Order::latest()->first();
        expect($order->currency)->toBe('MYR')
            ->and($order->base_currency)->toBe('MYR')
            ->and((float) $order->exchange_rate)->toBe(1.0)
            ->and($order->total_cents)->toBe($order->base_total_cents);
    });

    it('rejects unsupported currencies', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'XYZ',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['currency']);
    });

    it('applies a non-trivial exchange rate to the order total', function () {
        Http::fake([
            '*/latest*' => Http::response(['base' => 'MYR', 'rates' => ['USD' => 0.25]]),
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 800,
            'quantity' => 1,
        ]);
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'currency' => 'USD',
        ])->assertSuccessful();

        $order = Order::latest()->first();
        expect($order->exchange_rate)->toBe(0.25)
            ->and($order->currency)->toBe('USD')
            ->and($order->total_cents)->toBeLessThan($order->base_total_cents);
    });
});
