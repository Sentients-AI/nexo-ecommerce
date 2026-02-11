<?php

declare(strict_types=1);

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Request Refund API', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/orders/1/refunds', [
            'amount_cents' => 1000,
            'reason' => 'Test reason',
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/1/refunds', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount_cents', 'reason']);
    });

    it('validates amount_cents is positive', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/1/refunds', [
            'amount_cents' => 0,
            'reason' => 'Test reason',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount_cents']);
    });

    it('returns 404 for non-existent order', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/99999/refunds', [
            'amount_cents' => 1000,
            'reason' => 'Test reason',
        ]);

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'ORDER_NOT_FOUND');
    });

    it('rejects refund request for order not owned by user', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::factory()->paid()->create(['user_id' => $owner->id]);
        PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'status' => 'succeeded',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/v1/orders/{$order->id}/refunds", [
            'amount_cents' => 1000,
            'reason' => 'Test reason',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    });

    it('rejects refund for non-refundable order', function () {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_cents' => 10000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/refunds", [
            'amount_cents' => 5000,
            'reason' => 'Want my money back',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'ORDER_NOT_REFUNDABLE');
    });

    it('rejects refund exceeding refundable amount', function () {
        $user = User::factory()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total_cents' => 10000,
            'refunded_amount_cents' => 0,
        ]);
        PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'status' => 'succeeded',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/refunds", [
            'amount_cents' => 15000,
            'reason' => 'Want more than I paid',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'REFUND_AMOUNT_EXCEEDS_LIMIT');
    });

    it('creates refund request successfully', function () {
        $user = User::factory()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total_cents' => 10000,
            'refunded_amount_cents' => 0,
        ]);
        PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'status' => 'succeeded',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/refunds", [
            'amount_cents' => 5000,
            'reason' => 'Changed my mind',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'refund' => [
                'id',
                'order_id',
                'amount_cents',
                'currency',
                'status',
                'reason',
                'created_at',
            ],
        ]);
        $response->assertJsonPath('refund.amount_cents', 5000);
        $response->assertJsonPath('refund.reason', 'Changed my mind');
        $response->assertJsonPath('refund.status', RefundStatus::Requested->value);
    });

    it('allows partial refund when some amount already refunded', function () {
        $user = User::factory()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total_cents' => 10000,
            'refunded_amount_cents' => 3000,
        ]);
        PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'status' => 'succeeded',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/refunds", [
            'amount_cents' => 5000,
            'reason' => 'Partial refund for remaining amount',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('refund.amount_cents', 5000);
    });

    it('includes retryable flag in error responses', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders/99999/refunds', [
            'amount_cents' => 1000,
            'reason' => 'Test reason',
        ]);

        $response->assertNotFound();
        $response->assertJsonStructure([
            'error' => [
                'code',
                'message',
                'retryable',
            ],
        ]);
    });
});
