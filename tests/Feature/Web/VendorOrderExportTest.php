<?php

declare(strict_types=1);

use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('GET /vendor/orders/export', function () {
    it('requires authentication', function () {
        $this->get('/vendor/orders/export')
            ->assertRedirect('/en/login');
    });

    it('returns a CSV download response', function () {
        $this->actingAsUserInTenant();

        $response = $this->get('/vendor/orders/export');

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toContain('text/csv');
        expect($response->headers->get('Content-Disposition'))->toContain('orders-');
        expect($response->headers->get('Content-Disposition'))->toContain('.csv');
    });

    it('includes the CSV header row', function () {
        $this->actingAsUserInTenant();

        $content = $this->get('/vendor/orders/export')->streamedContent();

        expect($content)->toContain('Order Number');
        expect($content)->toContain('Customer Name');
        expect($content)->toContain('Total');
    });

    it('includes order data in the CSV', function () {
        $user = $this->actingAsUserInTenant();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_cents' => 4999,
            'subtotal_cents' => 4999,
            'discount_cents' => 0,
            'shipping_cost_cents' => 0,
        ]);

        $content = $this->get('/vendor/orders/export')->streamedContent();

        expect($content)->toContain($order->order_number);
        expect($content)->toContain('49.99');
        expect($content)->toContain($user->email);
    });

    it('filters by status when query param provided', function () {
        $this->actingAsUserInTenant();

        $paidOrder = Order::factory()->create(['status' => 'paid']);
        $pendingOrder = Order::factory()->create(['status' => 'pending']);

        $content = $this->get('/vendor/orders/export?status=paid')->streamedContent();

        expect($content)->toContain($paidOrder->order_number);
        expect($content)->not->toContain($pendingOrder->order_number);
    });

    it('returns only the header row when no orders exist', function () {
        $this->actingAsUserInTenant();

        $content = $this->get('/vendor/orders/export')->streamedContent();

        $lines = array_filter(explode("\n", mb_trim($content)));
        expect(count($lines))->toBe(1);
    });

    it('falls back to guest name and email when no user is attached', function () {
        $this->actingAsUserInTenant();

        Order::factory()->create([
            'user_id' => null,
            'guest_name' => 'Jane Guest',
            'guest_email' => 'jane@guest.com',
        ]);

        $content = $this->get('/vendor/orders/export')->streamedContent();

        expect($content)->toContain('Jane Guest');
        expect($content)->toContain('jane@guest.com');
    });

    it('exports all orders when no status filter applied', function () {
        $this->actingAsUserInTenant();

        Order::factory()->count(5)->create();

        $content = $this->get('/vendor/orders/export')->streamedContent();

        $lines = array_filter(explode("\n", mb_trim($content)));
        expect(count($lines))->toBe(6); // header + 5 data rows
    });
});
