<?php

declare(strict_types=1);

use App\Domain\Order\Actions\ShipOrderAction;
use App\Domain\Order\DTOs\ShipOrderData;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Notifications\OrderShippedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('ShipOrderAction', function () {
    it('marks a paid order as shipped with tracking info', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $data = new ShipOrderData(
            carrier: 'FedEx',
            trackingNumber: '1Z999AA10123456784',
            estimatedDeliveryAt: Carbon::parse('2026-04-01'),
        );

        $result = app(ShipOrderAction::class)->execute($order, $data);

        expect($result->status)->toBe(OrderStatus::Shipped)
            ->and($result->carrier)->toBe('FedEx')
            ->and($result->tracking_number)->toBe('1Z999AA10123456784')
            ->and($result->shipped_at)->not->toBeNull()
            ->and($result->estimated_delivery_at->toDateString())->toBe('2026-04-01');
    });

    it('marks a packed order as shipped', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Packed]);
        $data = new ShipOrderData(carrier: 'UPS', trackingNumber: 'TRACK123');

        $result = app(ShipOrderAction::class)->execute($order, $data);

        expect($result->status)->toBe(OrderStatus::Shipped);
    });

    it('sends a shipped notification to the customer', function () {
        Notification::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $data = new ShipOrderData(carrier: 'DHL', trackingNumber: 'DHL99887');

        app(ShipOrderAction::class)->execute($order, $data);

        Notification::assertSentTo(
            $order->user,
            OrderShippedNotification::class,
        );
    });

    it('throws when order is already shipped', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Shipped]);
        $data = new ShipOrderData(carrier: 'FedEx', trackingNumber: 'TRACK001');

        expect(fn () => app(ShipOrderAction::class)->execute($order, $data))
            ->toThrow(Exception::class, 'already been shipped');
    });

    it('throws when order cannot transition to shipped', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $data = new ShipOrderData(carrier: 'FedEx', trackingNumber: 'TRACK001');

        expect(fn () => app(ShipOrderAction::class)->execute($order, $data))
            ->toThrow(Exception::class);
    });

    it('ships without estimated delivery date', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $data = new ShipOrderData(carrier: 'USPS', trackingNumber: 'USPS555', estimatedDeliveryAt: null);

        $result = app(ShipOrderAction::class)->execute($order, $data);

        expect($result->status)->toBe(OrderStatus::Shipped)
            ->and($result->estimated_delivery_at)->toBeNull();
    });
});
