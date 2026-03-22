<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Product show page', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        Context::add('tenant_id', $this->tenant->id);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'view_count' => 0,
        ]);
    });

    it('increments view count on first visit', function () {
        $this->get("/en/products/{$this->product->slug}")
            ->assertOk();

        expect($this->product->fresh()->view_count)->toBe(1);
    });

    it('does not increment view count on subsequent visits in the same session', function () {
        $this->get("/en/products/{$this->product->slug}")->assertOk();
        $this->get("/en/products/{$this->product->slug}")->assertOk();
        $this->get("/en/products/{$this->product->slug}")->assertOk();

        expect($this->product->fresh()->view_count)->toBe(1);
    });

    it('increments view count again in a new session', function () {
        $this->get("/en/products/{$this->product->slug}")->assertOk();

        expect($this->product->fresh()->view_count)->toBe(1);

        // Simulate a new session
        $this->flushSession();

        $this->get("/en/products/{$this->product->slug}")->assertOk();

        expect($this->product->fresh()->view_count)->toBe(2);
    });

    it('tracks views independently per product', function () {
        $otherProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'view_count' => 0,
        ]);

        $this->get("/en/products/{$this->product->slug}")->assertOk();
        $this->get("/en/products/{$otherProduct->slug}")->assertOk();

        // Visit both again — neither should increment
        $this->get("/en/products/{$this->product->slug}")->assertOk();
        $this->get("/en/products/{$otherProduct->slug}")->assertOk();

        expect($this->product->fresh()->view_count)->toBe(1)
            ->and($otherProduct->fresh()->view_count)->toBe(1);
    });

    it('returns 404 for inactive products', function () {
        $inactive = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $this->get("/en/products/{$inactive->slug}")->assertNotFound();
    });
});
