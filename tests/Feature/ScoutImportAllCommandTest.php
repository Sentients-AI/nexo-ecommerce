<?php

declare(strict_types=1);

use App\Domain\Category\Models\Category;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

it('imports all searchable models successfully', function () {
    Product::factory()->count(2)->create(['is_active' => true]);
    Category::factory()->count(2)->create(['is_active' => true]);
    Order::factory()->count(2)->create();

    $this->artisan('scout:import-all')
        ->assertSuccessful()
        ->expectsOutputToContain('Importing Product')
        ->expectsOutputToContain('Importing Category')
        ->expectsOutputToContain('Importing Order')
        ->expectsOutputToContain('All searchable models imported successfully.');
});

it('flushes before importing when --fresh flag is used', function () {
    Product::factory()->create(['is_active' => true]);

    $this->artisan('scout:import-all --fresh')
        ->assertSuccessful()
        ->expectsOutputToContain('Flushing Product')
        ->expectsOutputToContain('Importing Product')
        ->expectsOutputToContain('All searchable models imported successfully.');
});
