<?php

declare(strict_types=1);

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Notifications\OrderShippedNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->actingAsUserInTenant();
});

describe('POST /vendor/orders/{order}/ship', function () {
    it('marks a paid order as shipped with valid data', function () {
        Notification::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->post("/vendor/orders/{$order->id}/ship", [
            'carrier' => 'FedEx',
            'tracking_number' => '1Z999AA10123456784',
            'estimated_delivery_at' => '2026-04-10',
        ])->assertRedirect();

        $order->refresh();

        expect($order->status)->toBe(OrderStatus::Shipped)
            ->and($order->carrier)->toBe('FedEx')
            ->and($order->tracking_number)->toBe('1Z999AA10123456784')
            ->and($order->shipped_at)->not->toBeNull()
            ->and($order->estimated_delivery_at->toDateString())->toBe('2026-04-10');

        Notification::assertSentTo($order->user, OrderShippedNotification::class);
    });

    it('ships without estimated delivery date', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->post("/vendor/orders/{$order->id}/ship", [
            'carrier' => 'DHL',
            'tracking_number' => 'DHL88776655',
        ])->assertRedirect();

        expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
    });

    it('validates required carrier and tracking number', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->post("/vendor/orders/{$order->id}/ship", [])
            ->assertSessionHasErrors(['carrier', 'tracking_number']);
    });

    it('rejects invalid estimated delivery date', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->post("/vendor/orders/{$order->id}/ship", [
            'carrier' => 'UPS',
            'tracking_number' => 'UPS123',
            'estimated_delivery_at' => 'not-a-date',
        ])->assertSessionHasErrors(['estimated_delivery_at']);
    });

    it('returns error when order cannot be shipped', function () {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $response = $this->post("/vendor/orders/{$order->id}/ship", [
            'carrier' => 'FedEx',
            'tracking_number' => 'TRACK001',
        ]);

        $response->assertRedirect();
        expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    });

    it('requires authentication', function () {
        auth()->logout();
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->post("/vendor/orders/{$order->id}/ship", [
            'carrier' => 'FedEx',
            'tracking_number' => 'TRACK001',
        ])->assertRedirect();

        expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    });
});

describe('GET /orders/{orderId} with tracking', function () {
    it('includes tracking fields in the order show response', function () {
        $user = $this->actingAsUserInTenant();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Shipped,
            'carrier' => 'FedEx',
            'tracking_number' => 'TRACK123',
            'shipped_at' => now(),
            'estimated_delivery_at' => '2026-04-15',
        ]);

        $this->get("/en/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Show')
                ->where('order.carrier', 'FedEx')
                ->where('order.tracking_number', 'TRACK123')
                ->has('order.shipped_at')
                ->where('order.estimated_delivery_at', '2026-04-15')
            );
    });

    it('includes null tracking fields when not shipped', function () {
        $user = $this->actingAsUserInTenant();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
        ]);

        $this->get("/en/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('order.tracking_number', null)
                ->where('order.carrier', null)
            );
    });
});
