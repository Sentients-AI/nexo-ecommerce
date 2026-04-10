<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Stock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->actingAsUserInTenant();
});

describe('Vendor dashboard', function () {
    it('renders dashboard page for authenticated vendor user', function () {
        $this->get('/vendor/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Dashboard')
                ->has('stats')
                ->has('recent_orders')
                ->has('chart_data')
            );
    });

    it('redirects guests to login', function () {
        auth()->logout();

        $this->get('/vendor/dashboard')
            ->assertRedirect();
    });
});

describe('Vendor orders page', function () {
    it('renders orders list', function () {
        Order::factory()->count(3)->create();

        $this->get('/vendor/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Orders')
                ->has('orders')
                ->has('statuses')
            );
    });

    it('filters orders by status', function () {
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'shipped']);

        $this->get('/vendor/orders?status=pending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('status_filter', 'pending')
                ->where('orders.total', 1)
            );
    });

    it('updates order status', function () {
        $order = Order::factory()->create(['status' => 'paid']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/orders/{$order->id}/status", ['status' => 'packed'])
            ->assertRedirect();

        expect($order->fresh()->status->value)->toBe('packed');
    });

    it('rejects invalid status update', function () {
        $order = Order::factory()->create(['status' => 'paid']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/orders/{$order->id}/status", ['status' => 'not_a_real_status'])
            ->assertSessionHasErrors('status');
    });
});

describe('Vendor products page', function () {
    it('renders products list', function () {
        Product::factory()->count(3)->create();

        $this->get('/vendor/products')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Products')
                ->has('products')
                ->has('stats')
            );
    });

    it('filters products by search', function () {
        Product::factory()->create(['name' => 'Blue Widget', 'sku' => 'BW-001']);
        Product::factory()->create(['name' => 'Red Widget', 'sku' => 'RW-001']);

        $this->get('/vendor/products?search=Blue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.total', 1)
            );
    });
});

describe('Vendor inventory page', function () {
    it('renders inventory with stock levels', function () {
        $product = Product::factory()->create();
        Stock::factory()->for($product)->create(['quantity_available' => 10]);

        $this->get('/vendor/inventory')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Inventory')
                ->has('products')
                ->has('stats')
            );
    });

    it('updates stock quantity', function () {
        $product = Product::factory()->create();
        $stock = Stock::factory()->for($product)->create(['quantity_available' => 5]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 50])
            ->assertRedirect();

        expect($stock->fresh()->quantity_available)->toBe(50);
    });

    it('creates a stock movement when quantity changes', function () {
        $product = Product::factory()->create();
        $stock = Stock::factory()->for($product)->create(['quantity_available' => 5]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 30]);

        expect(StockMovement::query()->where('stock_id', $stock->id)->count())->toBe(1)
            ->and(StockMovement::query()->where('stock_id', $stock->id)->first()->quantity)->toBe(25);
    });

    it('does not create a stock movement when quantity is unchanged', function () {
        $product = Product::factory()->create();
        $stock = Stock::factory()->for($product)->create(['quantity_available' => 10]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 10]);

        expect(StockMovement::query()->where('stock_id', $stock->id)->count())->toBe(0);
    });

    it('includes movement history in inventory index response', function () {
        $product = Product::factory()->create();
        $stock = Stock::factory()->for($product)->create(['quantity_available' => 10]);
        StockMovement::factory()->for($stock)->create(['product_id' => $product->id, 'quantity' => 5, 'reason' => 'Vendor manual adjustment']);

        $this->get('/vendor/inventory')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Inventory')
                ->has('products.data.0.stock.movements', 1)
            );
    });

    it('rejects negative stock quantity', function () {
        $product = Product::factory()->create();
        $stock = Stock::factory()->for($product)->create(['quantity_available' => 5]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => -1])
            ->assertSessionHasErrors('quantity_available');
    });
});

describe('Vendor customers page', function () {
    it('renders customers list', function () {
        $this->get('/vendor/customers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Customers')
                ->has('customers')
                ->has('stats')
            );
    });
});

describe('Vendor analytics page', function () {
    it('renders analytics data', function () {
        $this->get('/vendor/analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Analytics')
                ->has('daily_revenue')
                ->has('monthly_revenue')
                ->has('top_products')
                ->has('stats')
            );
    });
});

describe('Vendor promotions page', function () {
    it('renders promotions list', function () {
        $this->get('/vendor/promotions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Promotions')
                ->has('promotions')
            );
    });
});

describe('Vendor settings page', function () {
    it('renders settings with tenant info', function () {
        $this->get('/vendor/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Settings')
                ->has('tenant')
                ->has('usage')
            );
    });
});
