<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->actingAsUserInTenant();
});

describe('POST /vendor/products/bulk-action', function () {
    it('activates selected products', function () {
        $products = Product::factory()->count(3)->create(['is_active' => false]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'activate',
                'ids' => $products->pluck('id')->toArray(),
            ])->assertRedirect();

        expect(Product::query()->where('is_active', true)->count())->toBe(3);
    });

    it('deactivates selected products', function () {
        $products = Product::factory()->count(3)->create(['is_active' => true]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'deactivate',
                'ids' => $products->pluck('id')->toArray(),
            ])->assertRedirect();

        expect(Product::query()->where('is_active', false)->count())->toBe(3);
    });

    it('deletes selected products', function () {
        $products = Product::factory()->count(3)->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'delete',
                'ids' => $products->pluck('id')->toArray(),
            ])->assertRedirect();

        expect(Product::query()->whereIn('id', $products->pluck('id'))->count())->toBe(0);
    });

    it('only affects the specified ids — leaves others untouched', function () {
        $targets = Product::factory()->count(2)->create(['is_active' => false]);
        $others = Product::factory()->count(2)->create(['is_active' => false]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'activate',
                'ids' => $targets->pluck('id')->toArray(),
            ])->assertRedirect();

        expect(Product::query()->whereIn('id', $targets->pluck('id'))->where('is_active', true)->count())->toBe(2);
        expect(Product::query()->whereIn('id', $others->pluck('id'))->where('is_active', false)->count())->toBe(2);
    });

    it('returns a success flash message with count', function () {
        $products = Product::factory()->count(2)->create(['is_active' => false]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'activate',
                'ids' => $products->pluck('id')->toArray(),
            ])->assertSessionHas('success', '2 product(s) activated successfully.');
    });

    it('validates action is required', function () {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'ids' => [1],
            ])->assertSessionHasErrors(['action']);
    });

    it('validates action must be a known value', function () {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'publish',
                'ids' => [1],
            ])->assertSessionHasErrors(['action']);
    });

    it('validates ids are required', function () {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'activate',
            ])->assertSessionHasErrors(['ids']);
    });

    it('validates ids must not be empty', function () {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'activate',
                'ids' => [],
            ])->assertSessionHasErrors(['ids']);
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/products/bulk-action', [
                'action' => 'activate',
                'ids' => [1],
            ])->assertRedirect();
    });
});
