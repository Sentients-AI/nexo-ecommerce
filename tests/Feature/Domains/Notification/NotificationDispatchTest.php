<?php

declare(strict_types=1);

use App\Domain\Loyalty\Events\PointsEarned;
use App\Domain\Order\Events\OrderCancelled;
use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Events\OrderRefunded;
use App\Domain\Order\Models\Order;
use App\Domain\Refund\Events\RefundApproved;
use App\Domain\User\Models\User;
use App\Notifications\LoyaltyPointsEarnedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\RefundApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    Notification::fake();
    $this->user = User::factory()->create();
});

describe('OrderStatusChangedNotification', function () {
    it('notifies user when order is paid', function () {
        event(new OrderPaid(
            orderId: 1,
            userId: $this->user->id,
            tenantId: 1,
            orderNumber: 'ORD-001',
            totalCents: 10000,
        ));

        Notification::assertSentTo($this->user, OrderStatusChangedNotification::class, function ($n) {
            return $n->toArray($this->user)['status'] === 'paid'
                && $n->toArray($this->user)['order_number'] === 'ORD-001';
        });
    });

    it('notifies user when order is cancelled', function () {
        event(new OrderCancelled(
            orderId: 2,
            userId: $this->user->id,
            tenantId: 1,
            orderNumber: 'ORD-002',
        ));

        Notification::assertSentTo($this->user, OrderStatusChangedNotification::class, function ($n) {
            return $n->toArray($this->user)['status'] === 'cancelled';
        });
    });

    it('notifies user when order is refunded', function () {
        event(new OrderRefunded(
            orderId: 3,
            userId: $this->user->id,
            tenantId: 1,
            orderNumber: 'ORD-003',
            refundedAmountCents: 5000,
        ));

        Notification::assertSentTo($this->user, OrderStatusChangedNotification::class, function ($n) {
            return $n->toArray($this->user)['status'] === 'refunded';
        });
    });

    it('uses database and broadcast channels', function () {
        $notification = new OrderStatusChangedNotification(1, 'ORD-001', 'paid');

        expect($notification->via($this->user))->toContain('database')
            ->and($notification->via($this->user))->toContain('broadcast');
    });
});

describe('RefundApprovedNotification', function () {
    it('notifies order owner when refund is approved', function () {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        event(new RefundApproved(
            refundId: 1,
            orderId: $order->id,
            amountCents: 5000,
            currency: 'USD',
            approvedBy: 99,
        ));

        Notification::assertSentTo($this->user, RefundApprovedNotification::class, function ($n) {
            return $n->toArray($this->user)['amount_cents'] === 5000
                && $n->toArray($this->user)['currency'] === 'USD';
        });
    });

    it('toArray includes message and url', function () {
        $notification = new RefundApprovedNotification(orderId: 1, amountCents: 3000, currency: 'USD');
        $data = $notification->toArray($this->user);

        expect($data['type'])->toBe('refund_approved')
            ->and($data['message'])->toContain('30.00')
            ->and($data['url'])->toContain('/orders/1');
    });
});

describe('LoyaltyPointsEarnedNotification', function () {
    it('notifies user when loyalty points are earned', function () {
        event(new PointsEarned(
            userId: $this->user->id,
            points: 150,
            newBalance: 500,
        ));

        Notification::assertSentTo($this->user, LoyaltyPointsEarnedNotification::class, function ($n) {
            return $n->toArray($this->user)['points'] === 150
                && $n->toArray($this->user)['new_balance'] === 500;
        });
    });

    it('toArray includes message with points and balance', function () {
        $notification = new LoyaltyPointsEarnedNotification(points: 100, newBalance: 350);
        $data = $notification->toArray($this->user);

        expect($data['type'])->toBe('loyalty_points_earned')
            ->and($data['message'])->toContain('100')
            ->and($data['message'])->toContain('350');
    });
});
