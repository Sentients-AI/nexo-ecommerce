<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    $this->product = Product::factory()->create(['price_cents' => 10000, 'is_active' => true]);
});

function makeCart(Product $product, ?User $user = null): Cart
{
    $cart = Cart::factory()->create(['user_id' => $user?->id]);
    $cart->items()->create([
        'product_id' => $product->id,
        'price_cents_snapshot' => $product->price_cents,
        'tax_cents_snapshot' => 0,
        'quantity' => 1,
    ]);

    return $cart;
}

describe('POST /api/v1/promotions/preview — authenticated user', function () {
    it('returns discount for a valid code', function () {
        $user = User::factory()->create();
        $cart = makeCart($this->product, $user);
        $promotion = Promotion::factory()->fixed(1000)->create(['code' => 'SAVE10']);

        $this->actingAs($user)
            ->postJson('/api/v1/promotions/preview', ['cart_id' => $cart->id, 'code' => 'SAVE10'])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'promotion_name' => $promotion->name,
                'discount_cents' => 1000,
            ]);
    });

    it('returns valid=false for an invalid code', function () {
        $user = User::factory()->create();
        $cart = makeCart($this->product, $user);

        $this->actingAs($user)
            ->postJson('/api/v1/promotions/preview', ['cart_id' => $cart->id, 'code' => 'NOPE'])
            ->assertOk()
            ->assertJson(['valid' => false]);
    });
});

describe('POST /api/v1/promotions/preview — guest user', function () {
    it('returns discount for a valid code without authentication', function () {
        $cart = makeCart($this->product);
        Promotion::factory()->fixed(1000)->create(['code' => 'GUEST10']);

        $this->postJson('/api/v1/promotions/preview', ['cart_id' => $cart->id, 'code' => 'GUEST10'])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount_cents' => 1000]);
    });

    it('returns valid=false for an expired code', function () {
        $cart = makeCart($this->product);
        Promotion::factory()->fixed(1000)->expired()->create(['code' => 'OLD']);

        $this->postJson('/api/v1/promotions/preview', ['cart_id' => $cart->id, 'code' => 'OLD'])
            ->assertOk()
            ->assertJson(['valid' => false]);
    });

    it('skips per-user limit check for guests', function () {
        $cart = makeCart($this->product);
        Promotion::factory()->fixed(2000)->create([
            'code' => 'ONCE',
            'per_user_limit' => 1,
        ]);

        $this->postJson('/api/v1/promotions/preview', ['cart_id' => $cart->id, 'code' => 'ONCE'])
            ->assertOk()
            ->assertJson(['valid' => true]);
    });
});
