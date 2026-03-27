<?php

declare(strict_types=1);

use App\Domain\Category\Models\Category;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    // Force collection driver so tests do not require a live Typesense server.
    config(['scout.driver' => 'collection']);
});

describe('Product Search', function () {
    it('returns products matching search query', function () {
        Product::factory()->create(['name' => 'Unique Widget Alpha', 'is_active' => true]);
        Product::factory()->create(['name' => 'Other Item Beta', 'is_active' => true]);

        $response = $this->getJson('/api/v1/search/products?q=Unique+Widget+Alpha');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'sku', 'price_cents', 'currency', 'is_featured'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'facets' => ['categories', 'price_range' => ['min', 'max']],
        ]);
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Unique Widget Alpha');
    });

    it('returns all active products when no query', function () {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/search/products');

        $response->assertSuccessful();
        expect($response->json('meta.total'))->toBe(3);
        $response->assertJsonStructure([
            'facets' => ['categories', 'price_range' => ['min', 'max']],
        ]);
    });

    it('returns category facets with counts', function () {
        $beverages = Category::factory()->create(['name' => 'Beverages', 'slug' => 'beverages', 'is_active' => true]);
        $snacks = Category::factory()->create(['name' => 'Snacks', 'slug' => 'snacks', 'is_active' => true]);

        Product::factory()->count(3)->create(['is_active' => true, 'category_id' => $beverages->id]);
        Product::factory()->count(2)->create(['is_active' => true, 'category_id' => $snacks->id]);

        $response = $this->getJson('/api/v1/search/products');

        $response->assertSuccessful();

        $categories = collect($response->json('facets.categories'));
        $beveragesFacet = $categories->firstWhere('slug', 'beverages');
        $snacksFacet = $categories->firstWhere('slug', 'snacks');

        expect($beveragesFacet)->not->toBeNull()
            ->and($beveragesFacet['count'])->toBe(3)
            ->and($snacksFacet)->not->toBeNull()
            ->and($snacksFacet['count'])->toBe(2);
    });

    it('returns price range facet', function () {
        Product::factory()->create(['is_active' => true, 'price_cents' => 199]);
        Product::factory()->create(['is_active' => true, 'price_cents' => 5000]);
        Product::factory()->create(['is_active' => true, 'price_cents' => 9999]);

        $response = $this->getJson('/api/v1/search/products');

        $response->assertSuccessful();
        expect($response->json('facets.price_range.min'))->toBe(199)
            ->and($response->json('facets.price_range.max'))->toBe(9999);
    });

    it('filters by category slug', function () {
        $electronics = Category::factory()->create(['slug' => 'electronics', 'is_active' => true]);
        $other = Category::factory()->create(['slug' => 'clothing', 'is_active' => true]);
        Product::factory()->create(['is_active' => true, 'category_id' => $electronics->id]);
        Product::factory()->create(['is_active' => true, 'category_id' => $other->id]);

        $response = $this->getJson('/api/v1/search/products?category=electronics');

        $response->assertSuccessful();
        expect($response->json('meta.total'))->toBe(1);
    });

    it('filters by price range', function () {
        Product::factory()->create(['is_active' => true, 'price_cents' => 5000]);
        Product::factory()->create(['is_active' => true, 'price_cents' => 20000]);
        Product::factory()->create(['is_active' => true, 'price_cents' => 50000]);

        $response = $this->getJson('/api/v1/search/products?min_price=10000&max_price=30000');

        $response->assertSuccessful();
        expect($response->json('meta.total'))->toBe(1);
    });

    it('returns empty results for no match', function () {
        Product::factory()->create(['name' => 'Regular Product', 'is_active' => true]);

        $response = $this->getJson('/api/v1/search/products?q=xyznonexistentproduct999');

        $response->assertSuccessful();
        expect($response->json('meta.total'))->toBe(0);
    });

    it('is accessible without authentication', function () {
        $response = $this->getJson('/api/v1/search/products');

        $response->assertSuccessful();
    });
});

describe('Category Search', function () {
    it('returns categories matching search query', function () {
        Category::factory()->create(['name' => 'Electronics Gadgets', 'is_active' => true]);
        Category::factory()->create(['name' => 'Books Fiction', 'is_active' => true]);

        $response = $this->getJson('/api/v1/search/categories?q=Electronics+Gadgets');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'description'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Electronics Gadgets');
    });

    it('returns all active categories when no query', function () {
        Category::factory()->count(4)->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/search/categories');

        $response->assertSuccessful();
        expect($response->json('meta.total'))->toBe(4);
    });

    it('is accessible without authentication', function () {
        $response = $this->getJson('/api/v1/search/categories');

        $response->assertSuccessful();
    });
});

describe('Order Search', function () {
    it('searches orders by order number', function () {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id, 'order_number' => 'ORD-SEARCHME01']);
        Order::factory()->create(['user_id' => $user->id, 'order_number' => 'ORD-OTHER9999']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/search/orders?q=ORD-SEARCHME01');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'order_number', 'status', 'total_cents', 'currency', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $orderNumbers = collect($response->json('data'))->pluck('order_number');
        expect($orderNumbers)->toContain('ORD-SEARCHME01');
    });

    it('filters by status', function () {
        $user = User::factory()->create();
        Order::factory()->pending()->create(['user_id' => $user->id]);
        Order::factory()->paid()->create(['user_id' => $user->id]);
        Order::factory()->paid()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/search/orders?status=paid');

        $response->assertSuccessful();
        expect($response->json('meta.total'))->toBe(2);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/search/orders');

        $response->assertUnauthorized();
    });

    it('only returns current user orders', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Order::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(3)->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/search/orders');

        $response->assertSuccessful();
        expect($response->json('meta.total'))->toBe(2);
    });
});
