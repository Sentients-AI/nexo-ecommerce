<?php

declare(strict_types=1);

use App\Domain\Order\Enums\EarningStatus;
use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Events\OrderRefunded;
use App\Domain\Order\Listeners\AdjustEarningOnRefund;
use App\Domain\Order\Listeners\RecordVendorEarning;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\VendorEarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    config([
        'earnings.platform_fee_rate' => 0.02,
        'earnings.payout_hold_days' => 7,
    ]);
});

describe('RecordVendorEarning listener', function () {
    it('creates a VendorEarning record when an order is paid', function () {
        $order = Order::factory()->create([
            'subtotal_cents' => 10000,
            'tax_cents' => 1000,
            'shipping_cost_cents' => 500,
            'total_cents' => 11500,
        ]);

        $listener = new RecordVendorEarning;
        $listener->handle(new OrderPaid(
            $order->id,
            (int) $order->user_id,
            (int) $this->tenant->id,
            $order->order_number,
            $order->total_cents,
        ));

        $earning = VendorEarning::query()->where('order_id', $order->id)->first();

        expect($earning)->not->toBeNull()
            ->and($earning->gross_amount_cents)->toBe(11500)
            ->and($earning->platform_fee_cents)->toBe(200) // 2% of subtotal (10000)
            ->and($earning->net_amount_cents)->toBe(11300) // 11500 - 200
            ->and($earning->refunded_amount_cents)->toBe(0)
            ->and($earning->status)->toBe(EarningStatus::Pending);
    });

    it('sets available_at to now + payout_hold_days', function () {
        $order = Order::factory()->create(['subtotal_cents' => 5000, 'total_cents' => 5500]);

        $listener = new RecordVendorEarning;
        $listener->handle(new OrderPaid(
            $order->id,
            (int) $order->user_id,
            (int) $this->tenant->id,
            $order->order_number,
            $order->total_cents,
        ));

        $earning = VendorEarning::query()->where('order_id', $order->id)->first();

        expect($earning->available_at->toDateString())->toBe(now()->addDays(7)->toDateString());
    });

    it('does nothing when the order does not exist', function () {
        $listener = new RecordVendorEarning;
        $listener->handle(new OrderPaid(99999, 1, (int) $this->tenant->id, 'ORD-FAKE', 1000));

        expect(VendorEarning::query()->count())->toBe(0);
    });
});

describe('AdjustEarningOnRefund listener', function () {
    it('updates refunded_amount_cents on the matching earning', function () {
        $order = Order::factory()->create(['subtotal_cents' => 10000, 'total_cents' => 11000]);
        VendorEarning::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'net_amount_cents' => 10800,
            'refunded_amount_cents' => 0,
        ]);

        $listener = new AdjustEarningOnRefund;
        $listener->handle(new OrderRefunded(
            $order->id,
            (int) $order->user_id,
            (int) $this->tenant->id,
            $order->order_number,
            5000,
        ));

        $earning = VendorEarning::query()->where('order_id', $order->id)->first();
        expect($earning->refunded_amount_cents)->toBe(5000);
    });

    it('caps refunded_amount_cents at net_amount_cents', function () {
        $order = Order::factory()->create(['subtotal_cents' => 5000, 'total_cents' => 5500]);
        VendorEarning::factory()->forTenant($this->tenant)->create([
            'order_id' => $order->id,
            'net_amount_cents' => 5390,
            'refunded_amount_cents' => 0,
        ]);

        $listener = new AdjustEarningOnRefund;
        $listener->handle(new OrderRefunded(
            $order->id,
            (int) $order->user_id,
            (int) $this->tenant->id,
            $order->order_number,
            9999,
        ));

        $earning = VendorEarning::query()->where('order_id', $order->id)->first();
        expect($earning->refunded_amount_cents)->toBe(5390); // capped
    });

    it('does nothing when no earning exists for the order', function () {
        $order = Order::factory()->create();

        $listener = new AdjustEarningOnRefund;
        $listener->handle(new OrderRefunded(
            $order->id,
            (int) $order->user_id,
            (int) $this->tenant->id,
            $order->order_number,
            1000,
        ));

        expect(VendorEarning::query()->count())->toBe(0);
    });
});

describe('VendorEarning model', function () {
    it('computes effectiveNetCents as net minus refunded', function () {
        $earning = VendorEarning::factory()->forTenant($this->tenant)->make([
            'net_amount_cents' => 10000,
            'refunded_amount_cents' => 2000,
        ]);

        expect($earning->effectiveNetCents())->toBe(8000);
    });

    it('promotes pending earnings to available when their hold period has passed', function () {
        VendorEarning::factory()->forTenant($this->tenant)->count(3)->create([
            'status' => EarningStatus::Pending,
            'available_at' => now()->subDay(),
        ]);
        VendorEarning::factory()->forTenant($this->tenant)->create([
            'status' => EarningStatus::Pending,
            'available_at' => now()->addDays(3),
        ]);

        $this->actingAsUserInTenant();

        $this->get(route('vendor.earnings.index'))->assertOk();

        expect(VendorEarning::query()->where('status', EarningStatus::Available)->count())->toBe(3)
            ->and(VendorEarning::query()->where('status', EarningStatus::Pending)->count())->toBe(1);
    });
});

describe('GET /vendor/earnings', function () {
    it('renders the earnings page for an authenticated vendor user', function () {
        $this->actingAsUserInTenant();

        $this->get(route('vendor.earnings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Earnings')
                ->has('stats')
                ->has('earnings')
                ->has('platform_fee_rate')
            );
    });

    it('redirects guests to login', function () {
        $this->get(route('vendor.earnings.index'))->assertRedirect();
    });
});
