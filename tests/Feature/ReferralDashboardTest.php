<?php

declare(strict_types=1);

use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Referral\Models\ReferralUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Referral Dashboard', function () {
    it('redirects guests to login', function () {
        $response = $this->get('/en/referrals');

        $response->assertRedirect();
    });

    it('renders the referral dashboard for authenticated users', function () {
        $this->actingAsUserInTenant();

        $response = $this->get('/en/referrals');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Referral/Index')
            ->has('referralCode')
            ->has('referralCode.code')
            ->has('referralCode.shareable_url')
            ->has('stats')
            ->has('usages')
        );
    });

    it('auto-generates a referral code if none exists', function () {
        $user = $this->actingAsUserInTenant();

        expect(ReferralCode::query()->where('user_id', $user->id)->count())->toBe(0);

        $this->get('/en/referrals')->assertOk();

        expect(ReferralCode::query()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('returns existing referral code without creating a duplicate', function () {
        $user = $this->actingAsUserInTenant();

        $this->get('/en/referrals');
        $this->get('/en/referrals');

        expect(ReferralCode::query()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('shows referral usage history', function () {
        $user = $this->actingAsUserInTenant();
        $referee = $this->actingAsUserInTenant();

        $code = ReferralCode::factory()->create(['user_id' => $user->id]);
        ReferralUsage::factory()->create([
            'referral_code_id' => $code->id,
            'referrer_user_id' => $user->id,
            'referee_user_id' => $referee->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/en/referrals');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Referral/Index')
            ->has('usages', 1)
            ->has('stats.total_usages')
        );
    });
});
