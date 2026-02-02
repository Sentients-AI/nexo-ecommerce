<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Order\Actions\CreateOrderFromCart;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Contracts\PaymentGatewayService;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Refund\Actions\ProcessRefundAction;
use App\Domain\Refund\Actions\RequestRefundAction;
use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\Refund\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Partial Failure Scenarios', function () {
    it('invariant: no orphaned stock reservations when order creation fails', function () {
        $stock = Stock::factory()->create([
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        $cart = Cart::factory()->withProduct($stock->product_id, 5)->create();

        // First order succeeds
        $action = app(CreateOrderFromCart::class);
        $action->execute(new CreateOrderData(
            userId: (int) $cart->user_id,
            cartId: (string) $cart->id,
            currency: 'USD'
        ));

        // Try to create another order from the same completed cart
        try {
            $action->execute(new CreateOrderData(
                userId: (int) $cart->user_id,
                cartId: (string) $cart->id,
                currency: 'USD'
            ));
        } catch (Throwable) {
            // Expected
        }

        $stock->refresh();

        // Invariant: Only one order's worth of stock should be reserved
        expect($stock->quantity_reserved)->toBe(5)
            ->and($stock->quantity_available)->toBe(5)
            ->and(Order::query()->count())->toBe(1);
    });

    it('invariant: refund amount never exceeds paid amount', function () {
        $stock = Stock::factory()->create([
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        $cart = Cart::factory()->withProduct($stock->product_id, 1)->create();

        $action = app(CreateOrderFromCart::class);
        $order = $action->execute(new CreateOrderData(
            userId: (int) $cart->user_id,
            cartId: (string) $cart->id,
            currency: 'USD'
        ));

        // Create payment intent and mark as succeeded
        $paymentIntent = PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'currency' => $order->currency,
            'status' => PaymentStatus::Succeeded,
            'provider_reference' => 'pi_test_'.uniqid(),
        ]);

        $order->update(['status' => OrderStatus::Paid]);

        // Try to request refund for more than paid amount
        $requestRefund = app(RequestRefundAction::class);

        expect(fn () => $requestRefund->execute(
            $order,
            amountCents: $order->total_cents + 1000, // More than paid
            reason: 'Test overpayment refund'
        ))->toThrow(DomainException::class);

        expect(Refund::query()->count())->toBe(0);
    });

    it('invariant: total refunds never exceed order total', function () {
        $stock = Stock::factory()->create([
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        $cart = Cart::factory()->withProduct($stock->product_id, 1)->create();

        $action = app(CreateOrderFromCart::class);
        $order = $action->execute(new CreateOrderData(
            userId: (int) $cart->user_id,
            cartId: (string) $cart->id,
            currency: 'USD'
        ));

        PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'currency' => $order->currency,
            'status' => PaymentStatus::Succeeded,
            'provider_reference' => 'pi_test_'.uniqid(),
        ]);

        $order->update(['status' => OrderStatus::Paid]);

        $requestRefund = app(RequestRefundAction::class);

        // First partial refund should succeed
        $refund1 = $requestRefund->execute(
            $order->fresh(),
            amountCents: (int) ($order->total_cents * 0.5),
            reason: 'Partial refund 1'
        );

        expect($refund1)->toBeInstanceOf(Refund::class);

        // Mark first refund as succeeded
        $refund1->update(['status' => RefundStatus::Succeeded]);
        $order->update(['refunded_amount_cents' => $refund1->amount_cents]);

        // Second refund for remaining amount should succeed
        $refund2 = $requestRefund->execute(
            $order->fresh(),
            amountCents: (int) ($order->total_cents * 0.5),
            reason: 'Partial refund 2'
        );

        expect($refund2)->toBeInstanceOf(Refund::class);

        // Mark second refund as succeeded
        $refund2->update(['status' => RefundStatus::Succeeded]);
        $order->update(['refunded_amount_cents' => $order->refunded_amount_cents + $refund2->amount_cents]);

        // Third refund should fail - nothing left to refund
        expect(fn () => $requestRefund->execute(
            $order->fresh(),
            amountCents: 100,
            reason: 'Should fail'
        ))->toThrow(DomainException::class);
    });

    it('handles payment gateway failure gracefully', function () {
        $mockGateway = Mockery::mock(PaymentGatewayService::class);
        $mockGateway->shouldReceive('refund')
            ->andThrow(new RuntimeException('Gateway unavailable'));

        $this->app->instance(PaymentGatewayService::class, $mockGateway);

        $stock = Stock::factory()->create([
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        $cart = Cart::factory()->withProduct($stock->product_id, 1)->create();

        $action = app(CreateOrderFromCart::class);
        $order = $action->execute(new CreateOrderData(
            userId: (int) $cart->user_id,
            cartId: (string) $cart->id,
            currency: 'USD'
        ));

        PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->total_cents,
            'currency' => $order->currency,
            'status' => PaymentStatus::Succeeded,
            'provider_reference' => 'pi_test_'.uniqid(),
        ]);

        $order->update(['status' => OrderStatus::Paid]);

        $requestRefund = app(RequestRefundAction::class);
        $refund = $requestRefund->execute(
            $order->fresh(),
            amountCents: $order->total_cents,
            reason: 'Test refund'
        );

        $refund->update(['status' => RefundStatus::Approved]);

        $processRefund = app(ProcessRefundAction::class);

        expect(fn () => $processRefund->execute($refund->fresh()))
            ->toThrow(RuntimeException::class, 'Gateway unavailable');

        // Refund should be marked as failed
        $refund->refresh();
        expect($refund->status)->toBe(RefundStatus::Failed);
    });
});
