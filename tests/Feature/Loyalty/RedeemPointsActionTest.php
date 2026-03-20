<?php

declare(strict_types=1);

use App\Domain\Loyalty\Actions\RedeemPointsAction;
use App\Domain\Loyalty\DTOs\RedeemPointsData;
use App\Domain\Loyalty\Enums\TransactionType;
use App\Domain\Loyalty\Events\PointsRedeemed;
use App\Domain\Loyalty\Exceptions\InsufficientPointsException;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

it('decrements points balance when redeeming', function () {
    $user = User::factory()->create();
    LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);

    $action = app(RedeemPointsAction::class);
    $action->execute(new RedeemPointsData(
        userId: $user->id,
        points: 200,
        description: 'Redemption test',
    ));

    $account = LoyaltyAccount::query()->where('user_id', $user->id)->firstOrFail();
    expect($account->points_balance)->toBe(300);
    expect($account->total_points_redeemed)->toBe(200);
});

it('throws InsufficientPointsException when redeeming more than available', function () {
    $user = User::factory()->create();
    LoyaltyAccount::factory()->withPoints(50)->create(['user_id' => $user->id]);

    $action = app(RedeemPointsAction::class);

    expect(fn () => $action->execute(new RedeemPointsData(
        userId: $user->id,
        points: 200,
        description: 'Should fail',
    )))->toThrow(InsufficientPointsException::class);
});

it('creates a transaction with negative points when redeeming', function () {
    $user = User::factory()->create();
    LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);

    $action = app(RedeemPointsAction::class);
    $transaction = $action->execute(new RedeemPointsData(
        userId: $user->id,
        points: 100,
        description: 'Redeem for discount',
    ));

    expect($transaction->points)->toBe(-100);
    expect($transaction->type)->toBe(TransactionType::Redeemed);
    expect($transaction->balance_after)->toBe(400);
});

it('dispatches PointsRedeemed event when redeeming', function () {
    Event::fake();

    $user = User::factory()->create();
    LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);

    $action = app(RedeemPointsAction::class);
    $action->execute(new RedeemPointsData(
        userId: $user->id,
        points: 100,
        description: 'Event test',
    ));

    Event::assertDispatched(PointsRedeemed::class, function (PointsRedeemed $event) use ($user): bool {
        return $event->userId === $user->id
            && $event->points === 100
            && $event->newBalance === 400;
    });
});

it('does not modify balance when redeeming fails', function () {
    $user = User::factory()->create();
    LoyaltyAccount::factory()->withPoints(50)->create(['user_id' => $user->id]);

    $action = app(RedeemPointsAction::class);

    try {
        $action->execute(new RedeemPointsData(
            userId: $user->id,
            points: 100,
            description: 'Should fail',
        ));
    } catch (InsufficientPointsException) {
        // Expected
    }

    $account = LoyaltyAccount::query()->where('user_id', $user->id)->firstOrFail();
    expect($account->points_balance)->toBe(50);
});
