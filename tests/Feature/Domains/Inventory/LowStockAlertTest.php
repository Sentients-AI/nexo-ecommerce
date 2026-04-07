<?php

declare(strict_types=1);

use App\Domain\Inventory\Events\StockFellBelowThreshold;
use App\Domain\Inventory\Listeners\NotifyVendorOnLowStock;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Models\Product;
use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    config(['inventory.low_stock_threshold' => 5, 'inventory.low_stock_notifications' => true]);
});

describe('StockFellBelowThreshold event dispatched on inventory update', function () {
    it('dispatches StockFellBelowThreshold when stock crosses the threshold going down', function () {
        Event::fake([StockFellBelowThreshold::class]);

        $product = Product::factory()->create();
        $stock = Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 10, 'quantity_reserved' => 0]);

        $this->actingAsUserInTenant();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 3])
            ->assertRedirect();

        Event::assertDispatched(StockFellBelowThreshold::class, fn ($e) => $e->productId === $product->id
            && $e->newQuantity === 3
            && $e->threshold === 5);
    });

    it('does not dispatch StockFellBelowThreshold when stock was already at or below threshold', function () {
        Event::fake([StockFellBelowThreshold::class]);

        $product = Product::factory()->create();
        $stock = Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 3, 'quantity_reserved' => 0]);

        $this->actingAsUserInTenant();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 2])
            ->assertRedirect();

        Event::assertNotDispatched(StockFellBelowThreshold::class);
    });

    it('does not dispatch StockFellBelowThreshold when stock hits zero', function () {
        Event::fake([StockFellBelowThreshold::class]);

        $product = Product::factory()->create();
        $stock = Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 10, 'quantity_reserved' => 0]);

        $this->actingAsUserInTenant();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 0])
            ->assertRedirect();

        Event::assertNotDispatched(StockFellBelowThreshold::class);
    });

    it('does not dispatch StockFellBelowThreshold when stock is increased above threshold', function () {
        Event::fake([StockFellBelowThreshold::class]);

        $product = Product::factory()->create();
        $stock = Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 3, 'quantity_reserved' => 0]);

        $this->actingAsUserInTenant();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 20])
            ->assertRedirect();

        Event::assertNotDispatched(StockFellBelowThreshold::class);
    });
});

describe('NotifyVendorOnLowStock listener', function () {
    it('sends LowStockNotification to all tenant admins', function () {
        Notification::fake();

        $adminRole = Role::factory()->create(['name' => 'admin']);
        $admin1 = User::factory()->forTenant($this->tenant)->create(['role_id' => $adminRole->id]);
        $admin2 = User::factory()->forTenant($this->tenant)->create(['role_id' => $adminRole->id]);
        $product = Product::factory()->create();

        $listener = new NotifyVendorOnLowStock;
        $listener->handle(new StockFellBelowThreshold($product->id, (int) $this->tenant->id, 3, 5));

        Notification::assertSentTo($admin1, LowStockNotification::class);
        Notification::assertSentTo($admin2, LowStockNotification::class);
    });

    it('does not notify non-admin users', function () {
        Notification::fake();

        $vendorRole = Role::factory()->create(['name' => 'vendor']);
        $vendor = User::factory()->forTenant($this->tenant)->create(['role_id' => $vendorRole->id]);
        $product = Product::factory()->create();

        $listener = new NotifyVendorOnLowStock;
        $listener->handle(new StockFellBelowThreshold($product->id, (int) $this->tenant->id, 3, 5));

        Notification::assertNotSentTo($vendor, LowStockNotification::class);
    });

    it('does not send notifications when notifications are disabled in config', function () {
        config(['inventory.low_stock_notifications' => false]);
        Notification::fake();

        $adminRole = Role::factory()->create(['name' => 'admin']);
        $admin = User::factory()->forTenant($this->tenant)->create(['role_id' => $adminRole->id]);
        $product = Product::factory()->create();

        $listener = new NotifyVendorOnLowStock;
        $listener->handle(new StockFellBelowThreshold($product->id, (int) $this->tenant->id, 3, 5));

        Notification::assertNotSentTo($admin, LowStockNotification::class);
    });

    it('does nothing when product does not exist', function () {
        Notification::fake();

        $adminRole = Role::factory()->create(['name' => 'admin']);
        User::factory()->forTenant($this->tenant)->create(['role_id' => $adminRole->id]);

        $listener = new NotifyVendorOnLowStock;
        $listener->handle(new StockFellBelowThreshold(99999, (int) $this->tenant->id, 3, 5));

        Notification::assertNothingSent();
    });
});

describe('LowStockNotification content', function () {
    it('includes the correct product info in the notification array', function () {
        $product = Product::factory()->create(['name' => 'Test Widget']);
        $notification = new LowStockNotification($product, 3, 5);

        $data = $notification->toArray(new stdClass);

        expect($data['type'])->toBe('low_stock')
            ->and($data['product_id'])->toBe($product->id)
            ->and($data['product_name'])->toBe('Test Widget')
            ->and($data['quantity'])->toBe(3)
            ->and($data['threshold'])->toBe(5);
    });

    it('sends via mail and database channels', function () {
        $product = Product::factory()->create();
        $notification = new LowStockNotification($product, 3, 5);

        expect($notification->via(new stdClass))->toBe(['mail', 'database']);
    });
});
