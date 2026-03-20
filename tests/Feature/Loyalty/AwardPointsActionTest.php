<?php

declare(strict_types=1);

use App\Domain\Loyalty\Actions\AwardPointsAction;
use App\Domain\Loyalty\DTOs\AwardPointsData;
use App\Domain\Loyalty\Events\PointsEarned;
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

it('creates a loyalty account if none exists when awarding points', function () {
    $user = User::factory()->create();

    $action = app(AwardPointsAction::class);
    $action->execute(new AwardPointsData(
        userId: $user->id,
        points: 100,
        description: 'Test award',
    ));

    expect(LoyaltyAccount::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('increments points balance when awarding points', function () {
    $user = User::factory()->create();

    $action = app(AwardPointsAction::class);
    $action->execute(new AwardPointsData(
        userId: $user->id,
        points: 150,
        description: 'Initial award',
    ));

    $account = LoyaltyAccount::query()->where('user_id', $user->id)->firstOrFail();
    expect($account->points_balance)->toBe(150);
    expect($account->total_points_earned)->toBe(150);
});

it('accumulates points correctly across multiple awards', function () {
    $user = User::factory()->create();

    $action = app(AwardPointsAction::class);
    $action->execute(new AwardPointsData(userId: $user->id, points: 100, description: 'First award'));
    $action->execute(new AwardPointsData(userId: $user->id, points: 200, description: 'Second award'));
    $action->execute(new AwardPointsData(userId: $user->id, points: 50, description: 'Third award'));

    $account = LoyaltyAccount::query()->where('user_id', $user->id)->firstOrFail();
    expect($account->points_balance)->toBe(350);
    expect($account->total_points_earned)->toBe(350);
});

it('creates a loyalty transaction when awarding points', function () {
    $user = User::factory()->create();

    $action = app(AwardPointsAction::class);
    $transaction = $action->execute(new AwardPointsData(
        userId: $user->id,
        points: 100,
        description: 'Order reward',
        referenceType: 'order',
        referenceId: 42,
    ));

    expect($transaction->points)->toBe(100);
    expect($transaction->balance_after)->toBe(100);
    expect($transaction->description)->toBe('Order reward');
    expect($transaction->reference_type)->toBe('order');
    expect($transaction->reference_id)->toBe(42);
});

it('dispatches PointsEarned event when awarding points', function () {
    Event::fake();

    $user = User::factory()->create();

    $action = app(AwardPointsAction::class);
    $action->execute(new AwardPointsData(
        userId: $user->id,
        points: 100,
        description: 'Event test',
    ));

    Event::assertDispatched(PointsEarned::class, function (PointsEarned $event) use ($user): bool {
        return $event->userId === $user->id
            && $event->points === 100
            && $event->newBalance === 100;
    });
});
