<?php

declare(strict_types=1);

use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('GET /api/v1/loyalty', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/loyalty');

        $response->assertUnauthorized();
    });

    it('returns loyalty account for authenticated user', function () {
        $user = User::factory()->create();
        LoyaltyAccount::factory()->withPoints(250)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/loyalty');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'loyalty_account' => [
                'id',
                'points_balance',
                'total_points_earned',
                'total_points_redeemed',
                'points_value_cents',
            ],
        ]);
        expect($response->json('loyalty_account.points_balance'))->toBe(250);
    });

    it('creates a loyalty account if none exists', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/loyalty');

        $response->assertSuccessful();
        expect($response->json('loyalty_account.points_balance'))->toBe(0);
        expect(LoyaltyAccount::query()->where('user_id', $user->id)->exists())->toBeTrue();
    });
});

describe('GET /api/v1/loyalty/transactions', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/loyalty/transactions');

        $response->assertUnauthorized();
    });

    it('returns paginated transactions for authenticated user', function () {
        $user = User::factory()->create();
        $account = LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);
        LoyaltyTransaction::factory()->count(3)->create([
            'user_id' => $user->id,
            'loyalty_account_id' => $account->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/loyalty/transactions');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'type_label',
                    'points',
                    'balance_after',
                    'description',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
        expect($response->json('meta.total'))->toBe(3);
    });

    it('returns empty data when no transactions exist', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/loyalty/transactions');

        $response->assertSuccessful();
        expect($response->json('data'))->toBeEmpty();
    });

    it('does not return transactions from other users', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = LoyaltyAccount::factory()->create(['user_id' => $otherUser->id]);
        LoyaltyTransaction::factory()->count(5)->create([
            'user_id' => $otherUser->id,
            'loyalty_account_id' => $otherAccount->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/loyalty/transactions');

        $response->assertSuccessful();
        expect($response->json('data'))->toBeEmpty();
    });
});

describe('POST /api/v1/loyalty/redeem', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/loyalty/redeem', ['points' => 100]);

        $response->assertUnauthorized();
    });

    it('successfully redeems points', function () {
        $user = User::factory()->create();
        LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/loyalty/redeem', ['points' => 100]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'transaction' => [
                'id',
                'type',
                'points',
                'balance_after',
                'description',
                'created_at',
            ],
        ]);
        expect($response->json('transaction.points'))->toBe(-100);
        expect($response->json('transaction.balance_after'))->toBe(400);
    });

    it('fails with insufficient points', function () {
        $user = User::factory()->create();
        LoyaltyAccount::factory()->withPoints(50)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/loyalty/redeem', ['points' => 100]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'INSUFFICIENT_POINTS');
    });

    it('fails when below minimum redemption', function () {
        $user = User::factory()->create();
        LoyaltyAccount::factory()->withPoints(500)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $minimumRedemption = config('loyalty.minimum_redemption', 100);
        $belowMinimum = $minimumRedemption - 1;

        $response = $this->postJson('/api/v1/loyalty/redeem', ['points' => $belowMinimum]);

        $response->assertUnprocessable();
    });

    it('requires points field', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/loyalty/redeem', []);

        $response->assertUnprocessable();
    });
});
