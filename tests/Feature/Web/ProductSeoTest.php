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

describe('Product show SEO data', function () {
    it('passes seo prop with title from meta_title when set', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => 'Some Product',
            'meta_title' => 'Custom SEO Title',
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.title', 'Custom SEO Title')
            );
    });

    it('falls back to product name when meta_title is null', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => 'Fallback Name Product',
            'meta_title' => null,
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.title', 'Fallback Name Product')
            );
    });

    it('passes canonical url pointing to the correct product path', function () {
        $product = Product::factory()->create(['is_active' => true, 'slug' => 'my-product']);
        $expected = url('/en/products/my-product');

        $this->get('/en/products/my-product')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.canonical_url', $expected)
            );
    });

    it('passes seo description from meta_description when set', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'meta_description' => ['content' => 'My custom SEO description'],
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.description', 'My custom SEO description')
            );
    });

    it('falls back to short_description when meta_description is null', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'meta_description' => null,
            'short_description' => 'Short desc fallback',
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.description', 'Short desc fallback')
            );
    });

    it('passes the first image as seo.image', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'images' => ['https://example.com/img1.jpg', 'https://example.com/img2.jpg'],
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.image', 'https://example.com/img1.jpg')
            );
    });

    it('passes null image when product has no images', function () {
        $product = Product::factory()->create([
            'is_active' => true,
            'images' => null,
        ]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.image', null)
            );
    });
});

describe('Vendor dashboard trends', function () {
    it('passes real trend comparison stats', function () {
        $user = $this->actingAsUserInTenant();

        $this->get('/vendor/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('stats.revenue_yesterday')
                ->has('stats.revenue_last_month')
                ->has('stats.orders_yesterday')
            );
    });
});
