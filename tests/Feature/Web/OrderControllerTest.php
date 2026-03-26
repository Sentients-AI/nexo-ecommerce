<?php

declare(strict_types=1);

use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->actingAsUserInTenant();
});

describe('Customer orders list', function () {
    it('renders orders index page', function () {
        Order::factory()->count(2)->create(['user_id' => auth()->id()]);

        $this->get('/en/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Index')
                ->has('orders')
            );
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $this->get('/en/orders')
            ->assertRedirect();
    });
});

describe('Customer order detail', function () {
    it('renders order show page for own order', function () {
        $order = Order::factory()->create(['user_id' => auth()->id()]);

        $this->get("/en/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Show')
                ->has('order')
                ->where('order.id', $order->id)
            );
    });

    it('returns 404 for another users order', function () {
        $order = Order::factory()->create();

        $this->get("/en/orders/{$order->id}")
            ->assertNotFound();
    });

    it('returns 404 for non-existent order', function () {
        $this->get('/en/orders/99999')
            ->assertNotFound();
    });
});
