<?php

declare(strict_types=1);

use App\Domain\Referral\Actions\GenerateReferralCodeAction;
use App\Domain\Referral\DTOs\GenerateReferralCodeData;
use App\Domain\Referral\Models\ReferralCode;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    $this->action = app(GenerateReferralCodeAction::class);
});

it('generates a unique referral code', function () {
    $user = User::factory()->create();

    $referralCode = $this->action->execute(new GenerateReferralCodeData(
        userId: $user->id,
        referrerRewardPoints: 500,
        refereeDiscountPercent: 10,
        maxUses: 10,
        expiresAt: now()->addDays(30),
    ));

    expect($referralCode)->toBeInstanceOf(ReferralCode::class)
        ->and($referralCode->user_id)->toBe($user->id)
        ->and($referralCode->code)->not->toBeEmpty();

    $this->assertDatabaseHas('referral_codes', [
        'user_id' => $user->id,
        'code' => $referralCode->code,
    ]);
});

it('returns existing active code instead of creating a new one', function () {
    $user = User::factory()->create();
    $existingCode = ReferralCode::factory()->create(['user_id' => $user->id]);

    $result = $this->action->execute(new GenerateReferralCodeData(
        userId: $user->id,
        referrerRewardPoints: 500,
        refereeDiscountPercent: 10,
        maxUses: 10,
        expiresAt: now()->addDays(30),
    ));

    expect($result->id)->toBe($existingCode->id)
        ->and($result->code)->toBe($existingCode->code);

    expect(ReferralCode::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('generates a 12-character uppercase alphanumeric code', function () {
    $user = User::factory()->create();

    $referralCode = $this->action->execute(new GenerateReferralCodeData(
        userId: $user->id,
        referrerRewardPoints: 500,
        refereeDiscountPercent: 10,
        maxUses: null,
        expiresAt: null,
    ));

    expect($referralCode->code)->toHaveLength(12)
        ->and($referralCode->code)->toMatch('/^[A-Z0-9]{12}$/');
});

it('sets the correct expiry from data', function () {
    $user = User::factory()->create();
    $expiresAt = now()->addDays(30)->startOfSecond();

    $referralCode = $this->action->execute(new GenerateReferralCodeData(
        userId: $user->id,
        referrerRewardPoints: 500,
        refereeDiscountPercent: 10,
        maxUses: 10,
        expiresAt: $expiresAt,
    ));

    expect($referralCode->expires_at->timestamp)->toBe($expiresAt->timestamp);
});
