<?php

declare(strict_types=1);

use App\Domain\Order\Guards\OrderPaymentRequiredGuard;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Guards\PaymentAmountGuard;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\Refund\Guards\RefundAmountGuard;
use App\Domain\Refund\Guards\RefundProviderConfirmationGuard;
use App\Domain\Refund\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('PaymentAmountGuard', function () {
    it('passes when payment amount equals order total', function () {
        $order = Order::factory()->create(['total_cents' => 10000]);
        $payment = PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => 10000,
        ]);

        $guard = new PaymentAmountGuard($payment);

        expect($guard->check())->toBeTrue();
    });

    it('passes when payment amount is less than order total', function () {
        $order = Order::factory()->create(['total_cents' => 10000]);
        $payment = PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => 5000,
        ]);

        $guard = new PaymentAmountGuard($payment);

        expect($guard->check())->toBeTrue();
    });

    it('fails when payment amount exceeds order total', function () {
        $order = Order::factory()->create(['total_cents' => 10000]);
        $payment = PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => 15000,
        ]);

        $guard = new PaymentAmountGuard($payment);

        expect($guard->check())->toBeFalse();
        expect($guard->getViolationMessage())->toContain('exceeds order total');
    });

    it('throws exception when enforced with violation', function () {
        $order = Order::factory()->create(['total_cents' => 10000]);
        $payment = PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'amount' => 15000,
        ]);

        $guard = new PaymentAmountGuard($payment);
        $guard->enforce();
    })->throws(DomainException::class);
});

describe('RefundAmountGuard', function () {
    it('passes when refund amount is within limits', function () {
        $order = Order::factory()->create([
            'total_cents' => 10000,
            'refunded_amount_cents' => 0,
        ]);
        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_test',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => RefundStatus::Requested,
            'reason' => 'Test',
        ]);

        $guard = new RefundAmountGuard($refund);

        expect($guard->check())->toBeTrue();
    });

    it('passes when refund equals remaining amount', function () {
        $order = Order::factory()->create([
            'total_cents' => 10000,
            'refunded_amount_cents' => 3000,
        ]);
        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_test',
            'amount_cents' => 7000,
            'currency' => 'USD',
            'status' => RefundStatus::Requested,
            'reason' => 'Test',
        ]);

        $guard = new RefundAmountGuard($refund);

        expect($guard->check())->toBeTrue();
    });

    it('fails when total refunds would exceed order total', function () {
        $order = Order::factory()->create([
            'total_cents' => 10000,
            'refunded_amount_cents' => 8000,
        ]);
        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_test',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => RefundStatus::Requested,
            'reason' => 'Test',
        ]);

        $guard = new RefundAmountGuard($refund);

        expect($guard->check())->toBeFalse();
        expect($guard->getViolationMessage())->toContain('exceed order total');
    });
});

describe('OrderPaymentRequiredGuard', function () {
    it('passes for paid order with payment intent', function () {
        $order = Order::factory()->paid()->create();
        PaymentIntent::factory()->create(['order_id' => $order->id]);
        $order->load('paymentIntent');

        $guard = new OrderPaymentRequiredGuard($order);

        expect($guard->check())->toBeTrue();
    });

    it('passes for non-paid order without payment intent', function () {
        $order = Order::factory()->pending()->create();

        $guard = new OrderPaymentRequiredGuard($order);

        expect($guard->check())->toBeTrue();
    });

    it('fails for paid order without payment intent', function () {
        $order = Order::factory()->paid()->create();

        $guard = new OrderPaymentRequiredGuard($order);

        expect($guard->check())->toBeFalse();
        expect($guard->getViolationMessage())->toContain('has no payment intent');
    });
});

describe('RefundProviderConfirmationGuard', function () {
    it('passes for succeeded refund with provider reference', function () {
        $order = Order::factory()->create();
        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_test',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => RefundStatus::Succeeded,
            'reason' => 'Test',
            'provider_reference' => 're_test_123',
        ]);

        $guard = new RefundProviderConfirmationGuard($refund);

        expect($guard->check())->toBeTrue();
    });

    it('passes for non-succeeded refund without provider reference', function () {
        $order = Order::factory()->create();
        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_test',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => RefundStatus::Requested,
            'reason' => 'Test',
        ]);

        $guard = new RefundProviderConfirmationGuard($refund);

        expect($guard->check())->toBeTrue();
    });

    it('fails for succeeded refund without provider reference', function () {
        $order = Order::factory()->create();
        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_test',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => RefundStatus::Succeeded,
            'reason' => 'Test',
        ]);

        $guard = new RefundProviderConfirmationGuard($refund);

        expect($guard->check())->toBeFalse();
        expect($guard->getViolationMessage())->toContain('no provider reference');
    });
});
