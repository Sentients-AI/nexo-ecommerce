<?php

declare(strict_types=1);

use App\Domain\Bundle\Models\Bundle;
use App\Domain\Bundle\Models\BundleItem;
use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Bundle API', function () {
    it('lists active bundles', function () {
        Bundle::factory()->count(2)->create();
        Bundle::factory()->inactive()->create();

        $response = $this->getJson('/api/v1/bundles');

        $response->assertOk();
        $response->assertJsonCount(2, 'bundles');
    });

    it('shows a bundle by slug', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 10]);

        $bundle = Bundle::factory()->create();
        BundleItem::factory()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson("/api/v1/bundles/{$bundle->slug}");

        $response->assertOk();
        $response->assertJsonPath('bundle.slug', $bundle->slug);
        $response->assertJsonPath('bundle.in_stock', true);
        $response->assertJsonCount(1, 'bundle.items');
    });

    it('returns 404 for inactive bundle', function () {
        $bundle = Bundle::factory()->inactive()->create();

        $response = $this->getJson("/api/v1/bundles/{$bundle->slug}");

        $response->assertNotFound();
    });

    it('adds a bundle to cart', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 10]);

        $bundle = Bundle::factory()->create(['price_cents' => 4999]);
        BundleItem::factory()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson("/api/v1/bundles/{$bundle->slug}/cart");

        $response->assertOk();

        $cartItems = Cart::query()->first()?->items()->where('bundle_id', $bundle->id)->get();
        expect($cartItems)->toHaveCount(1);
        expect($cartItems->first()->price_cents_snapshot)->toBe(4999);
    });

    it('increments quantity when adding same bundle twice', function () {
        $this->actingAsUserInTenant();

        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 10]);

        $bundle = Bundle::factory()->create();
        BundleItem::factory()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->postJson("/api/v1/bundles/{$bundle->slug}/cart");
        $response = $this->postJson("/api/v1/bundles/{$bundle->slug}/cart");

        $response->assertOk();
        expect($response->json('cart.items.0.quantity'))->toBe(2);
    });

    it('rejects bundle when product is out of stock', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 0]);

        $bundle = Bundle::factory()->create();
        BundleItem::factory()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson("/api/v1/bundles/{$bundle->slug}/cart");

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    });
});

describe('Bundle storefront pages', function () {
    it('renders the bundles index page', function () {
        Bundle::factory()->count(2)->create();

        $response = $this->get('/en/bundles');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bundles/Index')
            ->has('bundles', 2)
        );
    });

    it('renders the bundle show page', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 5]);

        $bundle = Bundle::factory()->create();
        BundleItem::factory()->create(['bundle_id' => $bundle->id, 'product_id' => $product->id]);

        $response = $this->get("/en/bundles/{$bundle->slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bundles/Show')
            ->has('bundle')
        );
    });

    it('returns 404 for inactive bundle show page', function () {
        $bundle = Bundle::factory()->inactive()->create();

        $response = $this->get("/en/bundles/{$bundle->slug}");

        $response->assertNotFound();
    });
});
