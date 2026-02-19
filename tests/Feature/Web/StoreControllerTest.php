<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Review\Models\Review;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Store Show Page', function () {
    it('displays an active store with its products', function () {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'name' => 'Test Store',
            'description' => 'A great store.',
        ]);

        Context::add('tenant_id', $tenant->id);

        Product::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $response = $this->get("/en/stores/{$tenant->slug}");

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Stores/Show')
                ->has('store', fn ($store) => $store
                    ->where('name', 'Test Store')
                    ->where('slug', $tenant->slug)
                    ->where('description', 'A great store.')
                    ->where('total_products', 3)
                    ->etc()
                )
                ->has('products.data', 3)
            );
    });

    it('returns 404 for non-existent store', function () {
        $response = $this->get('/en/stores/non-existent-store');

        $response->assertNotFound();
    });

    it('returns 404 for inactive store', function () {
        $tenant = Tenant::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->get("/en/stores/{$tenant->slug}");

        $response->assertNotFound();
    });

    it('shows store average rating and review count', function () {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        Context::add('tenant_id', $tenant->id);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $users = User::factory()->count(3)->create();
        Review::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'user_id' => $users[0]->id,
            'is_approved' => true,
            'rating' => 4,
        ]);
        Review::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'user_id' => $users[1]->id,
            'is_approved' => true,
            'rating' => 4,
        ]);
        Review::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'user_id' => $users[2]->id,
            'is_approved' => false,
            'rating' => 1,
        ]);

        $response = $this->get("/en/stores/{$tenant->slug}");

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Stores/Show')
                ->has('store', fn ($store) => $store
                    ->where('total_reviews', 2)
                    ->where('average_rating', fn ($value) => (float) $value === 4.0)
                    ->etc()
                )
            );
    });

    it('only shows active products in the store', function () {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        Context::add('tenant_id', $tenant->id);

        Product::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        $response = $this->get("/en/stores/{$tenant->slug}");

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('products.data', 2)
            );
    });
});
