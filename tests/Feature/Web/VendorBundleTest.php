<?php

declare(strict_types=1);

use App\Domain\Bundle\Models\Bundle;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

describe('Vendor bundles index', function (): void {
    it('renders the bundles list page', function (): void {
        Bundle::factory()->count(3)->create();

        $this->get('/vendor/bundles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Bundles')
                ->has('bundles', 3)
            );
    });

    it('returns empty bundles when none exist', function (): void {
        $this->get('/vendor/bundles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Bundles')
                ->has('bundles', 0)
            );
    });

    it('redirects guests to login', function (): void {
        auth()->logout();

        $this->get('/vendor/bundles')->assertRedirect();
    });
});

describe('Vendor bundle create page', function (): void {
    it('renders the create bundle page with active products', function (): void {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $this->get('/vendor/bundles/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Bundles/Create')
                ->has('products', 3)
            );
    });
});

describe('Vendor bundle store', function (): void {
    it('creates a bundle with valid data', function (): void {
        $productA = Product::factory()->create(['is_active' => true]);
        $productB = Product::factory()->create(['is_active' => true]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/bundles', [
                'name' => 'Starter Kit',
                'description' => 'Everything you need to get started.',
                'price_cents' => 4999,
                'compare_at_price_cents' => 7999,
                'is_active' => true,
                'items' => [
                    ['product_id' => $productA->id, 'quantity' => 1, 'variant_id' => null],
                    ['product_id' => $productB->id, 'quantity' => 2, 'variant_id' => null],
                ],
            ])
            ->assertRedirect('/vendor/bundles');

        $this->assertDatabaseHas(Bundle::class, ['name' => 'Starter Kit', 'price_cents' => 4999]);
        $bundle = Bundle::query()->where('name', 'Starter Kit')->first();
        expect($bundle->items()->count())->toBe(2);
    });

    it('fails when fewer than 2 items are provided', function (): void {
        $product = Product::factory()->create(['is_active' => true]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/bundles', [
                'name' => 'Solo Bundle',
                'price_cents' => 1999,
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'variant_id' => null]],
            ])
            ->assertSessionHasErrors('items');
    });

    it('fails when required fields are missing', function (): void {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/bundles', [])
            ->assertSessionHasErrors(['name', 'price_cents', 'items']);
    });
});

describe('Vendor bundle edit page', function (): void {
    it('renders the edit page with bundle and products', function (): void {
        $productA = Product::factory()->create(['is_active' => true]);
        $productB = Product::factory()->create(['is_active' => true]);
        $bundle = Bundle::factory()->create();
        $bundle->items()->create(['product_id' => $productA->id, 'quantity' => 1]);
        $bundle->items()->create(['product_id' => $productB->id, 'quantity' => 2]);

        $this->get("/vendor/bundles/{$bundle->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Bundles/Edit')
                ->has('bundle')
                ->has('bundle.items', 2)
                ->has('products')
            );
    });
});

describe('Vendor bundle update', function (): void {
    it('updates the bundle and replaces items', function (): void {
        $productA = Product::factory()->create(['is_active' => true]);
        $productB = Product::factory()->create(['is_active' => true]);
        $bundle = Bundle::factory()->create(['name' => 'Old Name', 'price_cents' => 1000]);
        $bundle->items()->create(['product_id' => $productA->id, 'quantity' => 1]);
        $bundle->items()->create(['product_id' => $productB->id, 'quantity' => 1]);

        $productC = Product::factory()->create(['is_active' => true]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/bundles/{$bundle->id}", [
                'name' => 'New Name',
                'price_cents' => 5999,
                'is_active' => true,
                'items' => [
                    ['product_id' => $productA->id, 'quantity' => 2, 'variant_id' => null],
                    ['product_id' => $productC->id, 'quantity' => 1, 'variant_id' => null],
                ],
            ])
            ->assertRedirect('/vendor/bundles');

        expect($bundle->fresh()->name)->toBe('New Name')
            ->and($bundle->fresh()->price_cents)->toBe(5999)
            ->and($bundle->items()->count())->toBe(2);
    });

    it('fails update when fewer than 2 items are provided', function (): void {
        $productA = Product::factory()->create(['is_active' => true]);
        $productB = Product::factory()->create(['is_active' => true]);
        $bundle = Bundle::factory()->create();
        $bundle->items()->create(['product_id' => $productA->id, 'quantity' => 1]);
        $bundle->items()->create(['product_id' => $productB->id, 'quantity' => 1]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/bundles/{$bundle->id}", [
                'name' => 'Bad Update',
                'price_cents' => 1999,
                'items' => [['product_id' => $productA->id, 'quantity' => 1, 'variant_id' => null]],
            ])
            ->assertSessionHasErrors('items');
    });
});

describe('Vendor bundle destroy', function (): void {
    it('deletes a bundle', function (): void {
        $bundle = Bundle::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->delete("/vendor/bundles/{$bundle->id}")
            ->assertRedirect('/vendor/bundles');

        $this->assertDatabaseMissing(Bundle::class, ['id' => $bundle->id]);
    });

    it('returns 404 when bundle does not exist', function (): void {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->delete('/vendor/bundles/99999')
            ->assertNotFound();
    });
});
