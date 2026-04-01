<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Product show page share data', function () {
    it('passes canonical_url needed for share links', function () {
        $product = Product::factory()->create(['is_active' => true, 'slug' => 'shareable-product']);

        $this->get('/en/products/shareable-product')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.canonical_url', url('/en/products/shareable-product'))
            );
    });

    it('passes title needed for share link text', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => 'Great Shared Product',
            'meta_title' => null,
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.title', 'Great Shared Product')
            );
    });

    it('passes both title and canonical_url together for full share URL construction', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'slug' => 'combo-share-test',
            'meta_title' => 'Custom Share Title',
        ]);

        $this->get('/en/products/combo-share-test')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.title', 'Custom Share Title')
                ->where('seo.canonical_url', url('/en/products/combo-share-test'))
            );
    });
});
