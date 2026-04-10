<?php

declare(strict_types=1);

use App\Domain\Loyalty\Models\LoyaltyAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Loyalty Dashboard', function () {
    it('redirects guests to login', function () {
        $response = $this->get('/en/loyalty');

        $response->assertRedirect();
    });

    it('renders the loyalty page for authenticated users', function () {
        $this->actingAsUserInTenant();

        $response = $this->get('/en/loyalty');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Loyalty/Index')
            ->has('account')
            ->has('account.points_balance')
            ->has('transactions')
            ->has('tiers')
            ->has('config')
        );
    });

    it('creates a loyalty account if none exists', function () {
        $user = $this->actingAsUserInTenant();

        expect(LoyaltyAccount::query()->where('user_id', $user->id)->count())->toBe(0);

        $this->get('/en/loyalty')->assertOk();

        expect(LoyaltyAccount::query()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('shows correct balance from existing account', function () {
        $user = $this->actingAsUserInTenant();

        LoyaltyAccount::factory()->create([
            'user_id' => $user->id,
            'points_balance' => 1500,
            'total_points_earned' => 2000,
            'total_points_redeemed' => 500,
        ]);

        $response = $this->get('/en/loyalty');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Loyalty/Index')
            ->where('account.points_balance', 1500)
            ->where('account.total_points_earned', 2000)
            ->where('account.total_points_redeemed', 500)
        );
    });

    it('includes 3 tiers: Bronze, Silver, Gold', function () {
        $this->actingAsUserInTenant();

        $response = $this->get('/en/loyalty');

        $response->assertInertia(fn ($page) => $page
            ->has('tiers', 3)
            ->where('tiers.0.name', 'Bronze')
            ->where('tiers.1.name', 'Silver')
            ->where('tiers.2.name', 'Gold')
        );
    });
});
