<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Checkout summary', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        Context::add('tenant_id', $this->tenant->id);

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    });

    it('renders the checkout summary with correct stock available', function () {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 3,
            'tenant_id' => $this->tenant->id,
        ]);

        $cart = Cart::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->user)
            ->get('/en/checkout')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Summary')
                ->has('cart.items', 1, fn ($item) => $item
                    ->where('product.stock.available', 7)
                    ->etc()
                )
            );
    });

    it('redirects to cart when cart is empty', function () {
        Cart::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->user)
            ->get('/en/checkout')
            ->assertRedirect('/en/cart');
    });

    it('redirects to cart when no cart exists', function () {
        $this->actingAs($this->user)
            ->get('/en/checkout')
            ->assertRedirect('/en/cart');
    });

    it('handles product with no stock record gracefully', function () {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $cart = Cart::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->user)
            ->get('/en/checkout')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Summary')
                ->has('cart.items', 1, fn ($item) => $item
                    ->where('product.stock', null)
                    ->etc()
                )
            );
    });
});
