<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Flash sales index', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        Context::add('tenant_id', $this->tenant->id);
    });

    it('renders the flash sales page', function () {
        $this->get('/en/flash-sales')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('FlashSales/Index'));
    });

    it('returns only active running flash sales', function () {
        $active = Promotion::factory()->flashSale()->percentage(2000)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        // Inactive flash sale
        Promotion::factory()->flashSale()->percentage(1000)->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        // Expired flash sale
        Promotion::factory()->flashSale()->percentage(1000)->expired()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Not yet started
        Promotion::factory()->flashSale()->percentage(1000)->future()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->get('/en/flash-sales')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FlashSales/Index')
                ->has('flashSales', 1)
                ->where('flashSales.0.id', $active->id)
            );
    });

    it('includes product list and discounted price on flash sale', function () {
        $sale = Promotion::factory()->flashSale()->percentage(2000)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'price_cents' => 10000,
        ]);

        $sale->products()->attach($product->id);

        $this->get('/en/flash-sales')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('flashSales.0.products', 1)
                ->where('flashSales.0.products.0.id', $product->id)
                // 20% off 100.00 = 80.00 → 8000 cents
                ->where('flashSales.0.products.0.discounted_price_cents', 8000)
            );
    });

    it('includes seconds_remaining countdown', function () {
        Promotion::factory()->flashSale()->percentage(1000)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->get('/en/flash-sales')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('flashSales.0.seconds_remaining')
                ->where('flashSales.0.seconds_remaining', fn ($v) => $v > 0)
            );
    });

    it('returns empty flash sales when none are active', function () {
        $this->get('/en/flash-sales')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('flashSales', 0)
            );
    });
});

describe('Flash sale on product show page', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        Context::add('tenant_id', $this->tenant->id);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'price_cents' => 5000,
        ]);
    });

    it('passes null flashSale when no active flash sale targets the product', function () {
        $this->get("/en/products/{$this->product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('flashSale', null)
            );
    });

    it('passes flashSale data when an active flash sale targets the product', function () {
        $sale = Promotion::factory()->flashSale()->percentage(2000)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'scope' => 'product',
        ]);

        $sale->products()->attach($this->product->id);

        $this->get("/en/products/{$this->product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('flashSale')
                ->where('flashSale.discount_type', 'percentage')
                // 20% off 5000 = 4000
                ->where('flashSale.discounted_price_cents', 4000)
                ->where('flashSale.seconds_remaining', fn ($v) => $v > 0)
            );
    });

    it('does not pass flashSale when sale is inactive', function () {
        $sale = Promotion::factory()->flashSale()->percentage(2000)->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'scope' => 'product',
        ]);

        $sale->products()->attach($this->product->id);

        $this->get("/en/products/{$this->product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('flashSale', null));
    });
});
