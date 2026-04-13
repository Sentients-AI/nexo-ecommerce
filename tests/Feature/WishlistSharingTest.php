<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->setUpTenant();
});

describe('Wishlist sharing via URL', function (): void {
    it('loads products from ids query param without authentication', function (): void {
        $product = Product::factory()->create(['is_active' => true]);

        $this->get("/en/wishlist?ids={$product->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Wishlist/Index')
                ->has('products', 1)
            );
    });

    it('loads multiple products from comma-separated ids', function (): void {
        $p1 = Product::factory()->create(['is_active' => true]);
        $p2 = Product::factory()->create(['is_active' => true]);

        $this->get("/en/wishlist?ids={$p1->id},{$p2->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('products', 2));
    });

    it('excludes inactive products from shared wishlist', function (): void {
        $active = Product::factory()->create(['is_active' => true]);
        $inactive = Product::factory()->create(['is_active' => false]);

        $this->get("/en/wishlist?ids={$active->id},{$inactive->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('products', 1));
    });

    it('returns empty products when ids param is empty', function (): void {
        $this->get('/en/wishlist')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('products', 0));
    });

    it('returns empty products when ids param has invalid values', function (): void {
        $this->get('/en/wishlist?ids=abc,0,-1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('products', 0));
    });
});
