<?php

declare(strict_types=1);

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

it('returns valid XML with correct content-type', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml');
});

it('contains the urlset root element', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->getContent())->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
});

it('includes home pages for all supported locales', function () {
    $appUrl = config('app.url');

    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)
        ->toContain("{$appUrl}/en")
        ->toContain("{$appUrl}/ar")
        ->toContain("{$appUrl}/ms");
});

it('includes active product pages for each locale', function () {
    $product = Product::factory()->create(['is_active' => true, 'slug' => 'test-widget']);
    $appUrl = config('app.url');

    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)
        ->toContain("{$appUrl}/en/products/test-widget")
        ->toContain("{$appUrl}/ar/products/test-widget")
        ->toContain("{$appUrl}/ms/products/test-widget");
});

it('excludes inactive products', function () {
    Product::factory()->create(['is_active' => false, 'slug' => 'hidden-product']);

    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)->not->toContain('hidden-product');
});

it('includes active category filter URLs', function () {
    Category::factory()->create(['is_active' => true, 'slug' => 'electronics']);
    $appUrl = config('app.url');

    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)->toContain("{$appUrl}/en/products?category=electronics");
});

it('excludes inactive categories', function () {
    Category::factory()->create(['is_active' => false, 'slug' => 'hidden-cat']);

    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)->not->toContain('hidden-cat');
});

it('includes lastmod for products', function () {
    Product::factory()->create(['is_active' => true]);

    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)->toContain('<lastmod>');
});
