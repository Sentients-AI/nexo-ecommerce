<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Wishlist Page', function () {
    it('renders the wishlist page with no products when no ids provided', function () {
        $response = $this->get('/en/wishlist');

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Wishlist/Index')
                ->has('products', 0)
            );
    });

    it('returns matching active products for given ids', function () {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        Context::add('tenant_id', $tenant->id);

        $products = Product::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $ids = $products->pluck('id')->implode(',');
        $response = $this->get("/en/wishlist?ids={$ids}");

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Wishlist/Index')
                ->has('products', 3)
            );
    });

    it('excludes inactive products from wishlist', function () {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        Context::add('tenant_id', $tenant->id);

        $active = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $inactive = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        $ids = "{$active->id},{$inactive->id}";
        $response = $this->get("/en/wishlist?ids={$ids}");

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Wishlist/Index')
                ->has('products', 1)
            );
    });

    it('returns empty collection for non-existent ids', function () {
        $response = $this->get('/en/wishlist?ids=99999,99998');

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Wishlist/Index')
                ->has('products', 0)
            );
    });

    it('handles empty ids parameter gracefully', function () {
        $response = $this->get('/en/wishlist?ids=');

        $response->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Wishlist/Index')
                ->has('products', 0)
            );
    });
});
