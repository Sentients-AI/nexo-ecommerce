<?php

declare(strict_types=1);

use App\Domain\Loyalty\Actions\AwardPointsAction;
use App\Domain\Loyalty\Actions\ExpireLoyaltyPointsAction;
use App\Domain\Loyalty\DTOs\AwardPointsData;
use App\Domain\Loyalty\Enums\TransactionType;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    $this->action = app(ExpireLoyaltyPointsAction::class);
    $this->awardAction = app(AwardPointsAction::class);
});

it('expires points from transactions past their expiry date', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);

    LoyaltyTransaction::create([
        'tenant_id' => $account->tenant_id,
        'user_id' => $user->id,
        'loyalty_account_id' => $account->id,
        'type' => TransactionType::Earned,
        'points' => 500,
        'balance_after' => 500,
        'description' => 'Old points',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $this->action->execute();

    expect($account->fresh()->points_balance)->toBe(0);
});

it('creates an expired transaction audit entry', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::factory()->withPoints(300)->create(['user_id' => $user->id]);

    LoyaltyTransaction::create([
        'tenant_id' => $account->tenant_id,
        'user_id' => $user->id,
        'loyalty_account_id' => $account->id,
        'type' => TransactionType::Earned,
        'points' => 300,
        'balance_after' => 300,
        'description' => 'Earned points',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $this->action->execute();

    $expiredTx = LoyaltyTransaction::query()
        ->where('loyalty_account_id', $account->id)
        ->where('type', TransactionType::Expired)
        ->first();

    expect($expiredTx)->not->toBeNull()
        ->and($expiredTx->points)->toBe(-300)
        ->and($expiredTx->balance_after)->toBe(0);
});

it('does not expire points from transactions that have not yet expired', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::factory()->withPoints(200)->create(['user_id' => $user->id]);

    LoyaltyTransaction::create([
        'tenant_id' => $account->tenant_id,
        'user_id' => $user->id,
        'loyalty_account_id' => $account->id,
        'type' => TransactionType::Earned,
        'points' => 200,
        'balance_after' => 200,
        'description' => 'Fresh points',
        'expires_at' => Carbon::now()->addDays(30),
    ]);

    $this->action->execute();

    expect($account->fresh()->points_balance)->toBe(200);
});

it('does not expire points that have no expiry date', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::factory()->withPoints(150)->create(['user_id' => $user->id]);

    LoyaltyTransaction::create([
        'tenant_id' => $account->tenant_id,
        'user_id' => $user->id,
        'loyalty_account_id' => $account->id,
        'type' => TransactionType::Earned,
        'points' => 150,
        'balance_after' => 150,
        'description' => 'No-expiry points',
        'expires_at' => null,
    ]);

    $this->action->execute();

    expect($account->fresh()->points_balance)->toBe(150);
});

it('caps expired points at the current balance so balance cannot go negative', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::factory()->withPoints(100)->create(['user_id' => $user->id]);

    LoyaltyTransaction::create([
        'tenant_id' => $account->tenant_id,
        'user_id' => $user->id,
        'loyalty_account_id' => $account->id,
        'type' => TransactionType::Earned,
        'points' => 500,
        'balance_after' => 500,
        'description' => 'Large expired batch',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $this->action->execute();

    expect($account->fresh()->points_balance)->toBeGreaterThanOrEqual(0);
});

it('does not issue duplicate expiry debits on second run', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::factory()->withPoints(400)->create(['user_id' => $user->id]);

    LoyaltyTransaction::create([
        'tenant_id' => $account->tenant_id,
        'user_id' => $user->id,
        'loyalty_account_id' => $account->id,
        'type' => TransactionType::Earned,
        'points' => 400,
        'balance_after' => 400,
        'description' => 'Expired points',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $this->action->execute();
    $this->action->execute();

    expect($account->fresh()->points_balance)->toBe(0);
    expect(
        LoyaltyTransaction::query()
            ->where('loyalty_account_id', $account->id)
            ->where('type', TransactionType::Expired)
            ->count()
    )->toBe(1);
});

it('skips accounts with zero balance', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::factory()->create(['user_id' => $user->id, 'points_balance' => 0]);

    LoyaltyTransaction::create([
        'tenant_id' => $account->tenant_id,
        'user_id' => $user->id,
        'loyalty_account_id' => $account->id,
        'type' => TransactionType::Earned,
        'points' => 100,
        'balance_after' => 0,
        'description' => 'Already consumed',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $count = $this->action->execute();

    expect($count)->toBe(0);
});

it('sets expires_at on earned transactions when expiry_days is configured', function () {
    config(['loyalty.expiry_days' => 365]);

    $user = User::factory()->create();

    $transaction = $this->awardAction->execute(new AwardPointsData(
        userId: $user->id,
        points: 100,
        description: 'Test award',
    ));

    expect($transaction->expires_at)->not->toBeNull()
        ->and($transaction->expires_at->isFuture())->toBeTrue();
});

it('does not set expires_at when expiry_days is null', function () {
    config(['loyalty.expiry_days' => null]);

    $user = User::factory()->create();

    $transaction = $this->awardAction->execute(new AwardPointsData(
        userId: $user->id,
        points: 100,
        description: 'Test award',
    ));

    expect($transaction->expires_at)->toBeNull();
});
