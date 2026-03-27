<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
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

describe('Order invoice PDF', function () {
    it('downloads a PDF invoice for the authenticated users order', function () {
        $order = Order::factory()->create(['user_id' => auth()->id()]);

        $response = $this->get("/en/orders/{$order->id}/invoice");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', "attachment; filename=invoice-{$order->order_number}.pdf");
    });

    it('returns 404 when trying to download another users invoice', function () {
        $order = Order::factory()->create();

        $this->get("/en/orders/{$order->id}/invoice")
            ->assertNotFound();
    });

    it('returns 404 for a non-existent order invoice', function () {
        $this->get('/en/orders/99999/invoice')
            ->assertNotFound();
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $order = Order::factory()->create();

        $this->get("/en/orders/{$order->id}/invoice")
            ->assertRedirect();
    });
});

describe('Reorder', function () {
    it('adds all order items to the cart and redirects to cart', function () {
        $order = Order::factory()->create(['user_id' => auth()->id()]);
        OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

        $response = $this->post("/en/orders/{$order->id}/reorder");

        $response->assertRedirect('/en/cart');

        $cart = Cart::where('user_id', auth()->id())->first();
        expect($cart)->not->toBeNull()
            ->and(CartItem::where('cart_id', $cart->id)->count())->toBe(2);
    });

    it('returns 404 for another users order', function () {
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->post("/en/orders/{$order->id}/reorder")
            ->assertNotFound();
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $order = Order::factory()->create();

        $this->post("/en/orders/{$order->id}/reorder")
            ->assertRedirect();
    });
});
