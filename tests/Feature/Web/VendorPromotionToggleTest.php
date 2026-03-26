<?php

declare(strict_types=1);

use App\Domain\Promotion\Models\Promotion;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->actingAsUserInTenant();
});

describe('Vendor promotions index', function () {
    it('renders the promotions page', function () {
        Promotion::factory()->fixed(1000)->count(2)->create();
        Promotion::factory()->bogo(2, 1)->create();
        Promotion::factory()->tiered()->flashSale()->create();

        $this->get('/vendor/promotions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Promotions')
                ->has('promotions', 4)
                ->has('active_count')
                ->has('expired_count')
            );
    });

    it('exposes bogo and tiered fields in the promotions list', function () {
        Promotion::factory()->bogo(3, 1)->create(['name' => 'Buy 3 Get 1']);
        Promotion::factory()->tiered([
            ['min_cents' => 5000, 'discount_bps' => 500],
        ])->create(['name' => 'Spend More Save More']);

        $response = $this->get('/vendor/promotions')->assertOk();

        $promotions = $response->original->getData()['page']['props']['promotions'];

        $bogo = collect($promotions)->firstWhere('name', 'Buy 3 Get 1');
        expect($bogo['discount_type'])->toBe('bogo')
            ->and($bogo['buy_quantity'])->toBe(3)
            ->and($bogo['get_quantity'])->toBe(1);

        $tiered = collect($promotions)->firstWhere('name', 'Spend More Save More');
        expect($tiered['discount_type'])->toBe('tiered')
            ->and($tiered['tiers'])->toHaveCount(1);
    });

    it('includes time_remaining_seconds for flash sale promotions', function () {
        Promotion::factory()->fixed(1000)->flashSale()->create([
            'ends_at' => now()->addHours(3),
        ]);

        $response = $this->get('/vendor/promotions')->assertOk();

        $promotions = $response->original->getData()['page']['props']['promotions'];
        $flash = collect($promotions)->first();

        expect($flash['is_flash_sale'])->toBeTrue()
            ->and($flash['time_remaining_seconds'])->toBeGreaterThan(0);
    });
});

describe('Vendor promotion toggle', function () {
    it('disables an active promotion', function () {
        $promotion = Promotion::factory()->fixed(1000)->create(['is_active' => true]);

        $this->patch("/vendor/promotions/{$promotion->id}/toggle")
            ->assertRedirect();

        expect($promotion->fresh()->is_active)->toBeFalse();
    });

    it('enables a disabled promotion', function () {
        $promotion = Promotion::factory()->fixed(1000)->inactive()->create();

        $this->patch("/vendor/promotions/{$promotion->id}/toggle")
            ->assertRedirect();

        expect($promotion->fresh()->is_active)->toBeTrue();
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $promotion = Promotion::factory()->fixed(1000)->create();

        $this->patch("/vendor/promotions/{$promotion->id}/toggle")
            ->assertRedirect();

        expect($promotion->fresh()->is_active)->toBeTrue(); // unchanged
    });
});
