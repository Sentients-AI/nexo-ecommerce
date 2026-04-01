<?php

declare(strict_types=1);

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->actingAsUserInTenant();
});

describe('Vendor product create page', function () {
    it('renders the create product page with categories', function () {
        Category::factory()->count(3)->create();

        $this->get('/vendor/products/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Products/Create')
                ->has('categories', 3)
            );
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $this->get('/vendor/products/create')
            ->assertRedirect();
    });
});

describe('Vendor product store', function () {
    it('creates a product with valid data', function () {
        $category = Category::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products', [
                'name' => 'Test Headphones',
                'sku' => 'TH-001',
                'price_cents' => '49.99',
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->assertRedirect('/vendor/products');

        $product = Product::query()->where('sku', 'TH-001')->first();
        expect($product)->not->toBeNull()
            ->and($product->name)->toBe('Test Headphones')
            ->and((int) $product->price_cents)->toBe(4999)
            ->and($product->slug)->toBe('test-headphones');
    });

    it('creates stock record for new product', function () {
        $category = Category::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products', [
                'name' => 'Stock Test Product',
                'sku' => 'STP-001',
                'price_cents' => '10.00',
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->assertRedirect('/vendor/products');

        $product = Product::query()->where('sku', 'STP-001')->first();
        expect($product->stock)->not->toBeNull()
            ->and($product->stock->quantity_available)->toBe(0);
    });

    it('auto-generates slug from name when slug is blank', function () {
        $category = Category::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products', [
                'name' => 'My Cool Product',
                'sku' => 'MCP-001',
                'price_cents' => '5.00',
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ]);

        $product = Product::query()->where('sku', 'MCP-001')->first();
        expect($product->slug)->toBe('my-cool-product');
    });

    it('rejects missing required fields', function () {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products', [])
            ->assertSessionHasErrors(['name', 'sku', 'price_cents', 'category_id']);
    });

    it('rejects duplicate sku', function () {
        $category = Category::factory()->create();
        Product::factory()->create(['sku' => 'DUP-001']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products', [
                'name' => 'Another Product',
                'sku' => 'DUP-001',
                'price_cents' => '10.00',
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->assertSessionHasErrors(['sku']);
    });

    it('rejects invalid slug format', function () {
        $category = Category::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products', [
                'name' => 'Bad Slug Product',
                'sku' => 'BSP-001',
                'slug' => 'Has Spaces & Symbols!',
                'price_cents' => '10.00',
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->assertSessionHasErrors(['slug']);
    });
});

describe('Vendor product edit page', function () {
    it('renders the edit page with product data', function () {
        $product = Product::factory()->create();

        $this->get("/vendor/products/{$product->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Products/Edit')
                ->where('product.id', $product->id)
                ->where('product.name', $product->name)
                ->has('categories')
            );
    });
});

describe('Vendor product update', function () {
    it('updates a product with valid data', function () {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Old Name', 'sku' => 'OLD-001']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/products/{$product->id}", [
                'name' => 'New Name',
                'sku' => 'NEW-001',
                'price_cents' => '99.99',
                'category_id' => $category->id,
                'is_active' => false,
                'is_featured' => true,
            ])
            ->assertRedirect('/vendor/products');

        $updated = $product->fresh();
        expect($updated->name)->toBe('New Name')
            ->and($updated->sku)->toBe('NEW-001')
            ->and((int) $updated->price_cents)->toBe(9999)
            ->and($updated->is_active)->toBeFalse()
            ->and($updated->is_featured)->toBeTrue();
    });

    it('allows keeping same sku on update', function () {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['sku' => 'SAME-001']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/products/{$product->id}", [
                'name' => 'Updated Name',
                'sku' => 'SAME-001',
                'price_cents' => '20.00',
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->assertRedirect('/vendor/products');

        expect($product->fresh()->name)->toBe('Updated Name');
    });

    it('rejects updating to a sku owned by another product', function () {
        $category = Category::factory()->create();
        Product::factory()->create(['sku' => 'TAKEN-001']);
        $product = Product::factory()->create(['sku' => 'MINE-001']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/products/{$product->id}", [
                'name' => 'Some Name',
                'sku' => 'TAKEN-001',
                'price_cents' => '10.00',
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->assertSessionHasErrors(['sku']);
    });
});

describe('Vendor product destroy', function () {
    it('deletes a product', function () {
        $product = Product::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->delete("/vendor/products/{$product->id}")
            ->assertRedirect('/vendor/products');

        expect(Product::query()->find($product->id))->toBeNull();
    });
});
