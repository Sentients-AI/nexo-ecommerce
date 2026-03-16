<?php

declare(strict_types=1);

use App\Domain\Order\Actions\CancelOrder;
use App\Domain\Order\Actions\CreateOrderFromCart;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Events\PaymentSucceeded;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\Refund\Events\RefundSucceeded;
use App\Domain\Refund\Models\Refund;
use App\Events\OrderStatusUpdated;
use Database\Factories\CartFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

it('broadcasts order status updated when order is created', function () {
    Event::fake([OrderStatusUpdated::class]);

    $user = $this->actingAsUserInTenant();

    $cart = CartFactory::new()->create(['user_id' => $user->id]);
    $product = App\Domain\Product\Models\Product::factory()->create();
    App\Domain\Inventory\Models\Stock::factory()->create([
        'product_id' => $product->id,
        'quantity_available' => 5,
        'quantity_reserved' => 0,
    ]);
    $cart->items()->create([
        'product_id' => $product->id,
        'price_cents_snapshot' => 1000,
        'tax_cents_snapshot' => 100,
        'quantity' => 1,
    ]);

    app(CreateOrderFromCart::class)->execute(
        new CreateOrderData(userId: $user->id, cartId: (string) $cart->id, currency: 'USD')
    );

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($user) {
        return $event->userId === $user->id
            && $event->tenantId === $this->tenant->id
            && $event->status === OrderStatus::Pending->value;
    });
});

it('broadcasts order status updated when payment succeeds', function () {
    Event::fake([OrderStatusUpdated::class]);

    $user = $this->actingAsUserInTenant();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
    ]);
    $paymentIntent = PaymentIntent::factory()->create([
        'order_id' => $order->id,
    ]);

    PaymentSucceeded::dispatch(
        $paymentIntent->id,
        $order->id,
        $order->total_cents,
        'USD',
    );

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order, $user) {
        return $event->orderId === $order->id
            && $event->userId === $user->id
            && $event->status === OrderStatus::Paid->value;
    });
});

it('broadcasts order status updated when order is cancelled', function () {
    Event::fake([OrderStatusUpdated::class]);

    $user = $this->actingAsUserInTenant();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
    ]);

    app(CancelOrder::class)->execute($order);

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order) {
        return $event->orderId === $order->id
            && $event->status === 'cancelled';
    });
});

it('broadcasts order status updated when refund succeeds', function () {
    Event::fake([OrderStatusUpdated::class]);

    $user = $this->actingAsUserInTenant();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Paid,
        'total_cents' => 5000,
    ]);
    $paymentIntent = PaymentIntent::factory()->create([
        'order_id' => $order->id,
    ]);

    $refund = Refund::query()->create([
        'order_id' => $order->id,
        'payment_intent_id' => $paymentIntent->id,
        'amount_cents' => 5000,
        'currency' => 'USD',
        'status' => RefundStatus::Requested,
        'reason' => 'Test refund',
    ]);

    RefundSucceeded::dispatch(
        $refund->id,
        $order->id,
        5000,
        'USD',
    );

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order) {
        return $event->orderId === $order->id;
    });
});

it('order status updated event broadcasts on the correct channels', function () {
    $event = new OrderStatusUpdated(
        orderId: 1,
        userId: 42,
        tenantId: 7,
        orderNumber: 'ORD-TEST',
        status: 'paid',
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0]->name)->toBe('private-orders.42');
    expect($channels[1]->name)->toBe('private-tenant.7.orders');
});

it('order status updated event broadcasts the correct payload', function () {
    $event = new OrderStatusUpdated(
        orderId: 1,
        userId: 42,
        tenantId: 7,
        orderNumber: 'ORD-TEST',
        status: 'paid',
    );

    expect($event->broadcastAs())->toBe('order.status.updated');
    expect($event->broadcastWith())->toMatchArray([
        'order_id' => 1,
        'order_number' => 'ORD-TEST',
        'status' => 'paid',
    ]);
});
