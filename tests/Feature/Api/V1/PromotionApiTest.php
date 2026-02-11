<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\DTOs\ProviderResponse;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    $this->user = User::factory()->create();
    $this->cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $this->product = Product::factory()->create(['price_cents' => 10000]);
    $this->cart->items()->create([
        'product_id' => $this->product->id,
        'price_cents_snapshot' => 10000,
        'tax_cents_snapshot' => 1000,
        'quantity' => 1,
    ]);

    // Mock the payment gateway service for checkout tests
    $mock = Mockery::mock(PaymentGatewayService::class);
    $mock->shouldReceive('createIntent')
        ->andReturnUsing(fn ($intent) => new ProviderResponse(
            provider: 'test',
            reference: 'pi_test_'.uniqid(),
            clientSecret: 'cs_test_'.uniqid(),
        ));
    $this->app->instance(PaymentGatewayService::class, $mock);
});

describe('Apply Promotion Endpoint', function () {
    it('applies valid promotion code', function () {
        $promotion = Promotion::factory()->fixed(2000)->create([
            'code' => 'SAVE20',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/apply-promotion', [
                'code' => 'SAVE20',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('eligible', true);
        $response->assertJsonPath('discount_cents', 2000);
        $response->assertJsonPath('promotion.id', $promotion->id);
    });

    it('returns error for invalid promotion code', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/apply-promotion', [
                'code' => 'INVALID',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
    });

    it('returns error for expired promotion code', function () {
        Promotion::factory()->fixed(2000)->expired()->create([
            'code' => 'EXPIRED',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/apply-promotion', [
                'code' => 'EXPIRED',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertStatus(422);
    });

    it('returns error for cart not owned by user', function () {
        $otherUser = User::factory()->create();
        $otherCart = Cart::factory()->create(['user_id' => $otherUser->id]);

        $promotion = Promotion::factory()->fixed(2000)->create([
            'code' => 'SAVE20',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/apply-promotion', [
                'code' => 'SAVE20',
                'cart_id' => $otherCart->id,
            ]);

        $response->assertForbidden();
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/cart/apply-promotion', [
            'code' => 'SAVE20',
            'cart_id' => $this->cart->id,
        ]);

        $response->assertUnauthorized();
    });
});

describe('Active Promotions Endpoint', function () {
    it('returns active auto-apply promotions', function () {
        $autoApply1 = Promotion::factory()->fixed(1000)->autoApply()->create();
        $autoApply2 = Promotion::factory()->percentage(500)->autoApply()->create();
        $codeOnly = Promotion::factory()->fixed(2000)->create(['code' => 'CODE123']);
        $inactive = Promotion::factory()->fixed(1500)->autoApply()->inactive()->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/promotions/active');

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'promotions');

        $ids = collect($response->json('promotions'))->pluck('id')->toArray();
        expect($ids)->toContain($autoApply1->id);
        expect($ids)->toContain($autoApply2->id);
        expect($ids)->not->toContain($codeOnly->id);
        expect($ids)->not->toContain($inactive->id);
    });
});

describe('Validate Promotion Endpoint', function () {
    it('validates valid promotion code', function () {
        $promotion = Promotion::factory()->fixed(2000)->create([
            'code' => 'VALID',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/validate-promotion', [
                'code' => 'VALID',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('discount_cents', 2000);
    });

    it('returns valid false for invalid code', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cart/validate-promotion', [
                'code' => 'INVALID',
                'cart_id' => $this->cart->id,
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('valid', false);
    });
});

describe('Checkout with Promotion', function () {
    beforeEach(function () {
        // Create stock for the product
        $this->product->stock()->create([
            'quantity_available' => 100,
            'quantity_reserved' => 0,
        ]);
    });

    it('applies promotion code during checkout', function () {
        $promotion = Promotion::factory()->fixed(2000)->create([
            'code' => 'CHECKOUT20',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', [
                'cart_id' => $this->cart->id,
                'currency' => 'MYR',
                'promotion_code' => 'CHECKOUT20',
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('order.discount_cents', 2000);
        $response->assertJsonPath('order.promotion_id', $promotion->id);

        // Verify promotion usage was recorded
        $this->assertDatabaseHas('promotion_usages', [
            'promotion_id' => $promotion->id,
            'user_id' => $this->user->id,
            'discount_cents' => 2000,
        ]);

        // Verify promotion usage count was incremented
        expect($promotion->fresh()->usage_count)->toBe(1);
    });

    it('applies best auto-apply promotion during checkout without code', function () {
        $smallPromotion = Promotion::factory()->fixed(500)->autoApply()->create();
        $largePromotion = Promotion::factory()->fixed(1500)->autoApply()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', [
                'cart_id' => $this->cart->id,
                'currency' => 'MYR',
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('order.discount_cents', 1500);
        $response->assertJsonPath('order.promotion_id', $largePromotion->id);
    });

    it('completes checkout without promotion', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/checkout', [
                'cart_id' => $this->cart->id,
                'currency' => 'MYR',
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('order.discount_cents', 0);
        $response->assertJsonPath('order.promotion_id', null);
    });
});
