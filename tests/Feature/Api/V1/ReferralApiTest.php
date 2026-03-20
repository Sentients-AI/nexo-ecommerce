<?php

declare(strict_types=1);

use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Referral\Models\ReferralUsage;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('GET /api/v1/referral', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/referral');

        $response->assertUnauthorized();
    });

    it('returns existing referral code if one exists', function () {
        $user = User::factory()->create();
        $referralCode = ReferralCode::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/referral');

        $response->assertSuccessful();
        $response->assertJsonPath('referral_code.code', $referralCode->code);
    });

    it('creates a new code if none exists', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/referral');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'referral_code' => [
                'code',
                'shareable_url',
                'status',
                'referrer_reward_points',
                'referee_discount_percent',
                'max_uses',
                'used_count',
                'expires_at',
                'is_active',
            ],
        ]);
        expect(ReferralCode::query()->where('user_id', $user->id)->exists())->toBeTrue();
    });
});

describe('GET /api/v1/referral/stats', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/referral/stats');

        $response->assertUnauthorized();
    });

    it('returns correct stats', function () {
        $referrer = User::factory()->create();
        $referralCode = ReferralCode::factory()->create(['user_id' => $referrer->id]);

        $referee1 = User::factory()->create();
        $referee2 = User::factory()->create();

        ReferralUsage::factory()->create([
            'referral_code_id' => $referralCode->id,
            'referrer_user_id' => $referrer->id,
            'referee_user_id' => $referee1->id,
            'referrer_points_awarded' => 500,
        ]);
        ReferralUsage::factory()->create([
            'referral_code_id' => $referralCode->id,
            'referrer_user_id' => $referrer->id,
            'referee_user_id' => $referee2->id,
            'referrer_points_awarded' => 500,
        ]);

        Sanctum::actingAs($referrer);

        $response = $this->getJson('/api/v1/referral/stats');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'stats' => [
                'total_usages',
                'total_points_earned_from_referrals',
                'usages',
            ],
        ]);
        expect($response->json('stats.total_usages'))->toBe(2);
    });
});

describe('POST /api/v1/referral/apply', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/referral/apply', ['code' => 'TESTCODE12AB']);

        $response->assertUnauthorized();
    });

    it('successfully applies a valid code', function () {
        $referrer = User::factory()->create();
        $referralCode = ReferralCode::factory()->create(['user_id' => $referrer->id]);
        $referee = User::factory()->create();
        LoyaltyAccount::factory()->create(['user_id' => $referrer->id]);

        Sanctum::actingAs($referee);

        $response = $this->postJson('/api/v1/referral/apply', ['code' => $referralCode->code]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'usage' => [
                'referrer_points_awarded',
                'referee_discount_percent',
                'referee_coupon_code',
            ],
        ]);
    });

    it('fails with an expired code', function () {
        $referrer = User::factory()->create();
        $referralCode = ReferralCode::factory()->expired()->create(['user_id' => $referrer->id]);
        $referee = User::factory()->create();

        Sanctum::actingAs($referee);

        $response = $this->postJson('/api/v1/referral/apply', ['code' => $referralCode->code]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'REFERRAL_CODE_EXPIRED');
    });

    it('fails with an exhausted code', function () {
        $referrer = User::factory()->create();
        $referralCode = ReferralCode::factory()->exhausted()->create(['user_id' => $referrer->id]);
        $referee = User::factory()->create();

        Sanctum::actingAs($referee);

        $response = $this->postJson('/api/v1/referral/apply', ['code' => $referralCode->code]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'REFERRAL_CODE_EXHAUSTED');
    });

    it('fails with an invalid or non-existent code', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/referral/apply', ['code' => 'NONEXISTENT1']);

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'REFERRAL_CODE_INVALID');
    });

    it('fails when user tries to use their own code', function () {
        $user = User::factory()->create();
        $referralCode = ReferralCode::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/referral/apply', ['code' => $referralCode->code]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'SELF_REFERRAL');
    });

    it('fails when user has already used the same code', function () {
        $referrer = User::factory()->create();
        $referralCode = ReferralCode::factory()->create(['user_id' => $referrer->id]);
        $referee = User::factory()->create();

        ReferralUsage::factory()->create([
            'referral_code_id' => $referralCode->id,
            'referrer_user_id' => $referrer->id,
            'referee_user_id' => $referee->id,
        ]);

        Sanctum::actingAs($referee);

        $response = $this->postJson('/api/v1/referral/apply', ['code' => $referralCode->code]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'REFERRAL_ALREADY_USED');
    });

    it('awards loyalty points to the referrer after applying code', function () {
        $referrer = User::factory()->create();
        $referralCode = ReferralCode::factory()->create([
            'user_id' => $referrer->id,
            'referrer_reward_points' => 500,
        ]);
        $referee = User::factory()->create();

        Sanctum::actingAs($referee);

        $this->postJson('/api/v1/referral/apply', ['code' => $referralCode->code]);

        $loyaltyAccount = LoyaltyAccount::query()->where('user_id', $referrer->id)->first();
        expect($loyaltyAccount)->not->toBeNull()
            ->and($loyaltyAccount->points_balance)->toBe(500);
    });

    it('fails validation when code is missing', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/referral/apply', []);

        $response->assertUnprocessable();
    });
});

describe('POST /api/v1/referral/regenerate', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/referral/regenerate');

        $response->assertUnauthorized();
    });

    it('generates a new code and deactivates the old one', function () {
        $user = User::factory()->create();
        $oldCode = ReferralCode::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/referral/regenerate');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'referral_code' => ['code'],
        ]);

        $oldCode->refresh();
        expect($oldCode->is_active)->toBeFalse();
        expect($response->json('referral_code.code'))->not->toBe($oldCode->code);
    });
});
