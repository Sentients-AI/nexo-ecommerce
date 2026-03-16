<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Domain\Inventory\Actions\ReleaseStockAction;
use App\Domain\Inventory\Actions\ReserveStock;
use App\Domain\Inventory\DTOs\AdjustStockData;
use App\Domain\Inventory\DTOs\ReserveStockData;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Product\Actions\ChangePriceAction;
use App\Domain\Product\DTOs\ChangePriceData;
use App\Domain\Product\Models\Product;
use App\Events\InventoryStockUpdated;
use App\Events\ProductPriceUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

it('broadcasts stock updated when stock is adjusted', function () {
    Event::fake([InventoryStockUpdated::class]);

    $product = Product::factory()->create();
    $stock = Stock::factory()->create([
        'product_id' => $product->id,
        'quantity_available' => 10,
        'quantity_reserved' => 0,
    ]);

    app(AdjustStockAction::class)->execute(new AdjustStockData(
        productId: (string) $product->id,
        quantityChange: 5,
        reason: 'Restock',
    ));

    Event::assertDispatched(InventoryStockUpdated::class, function (InventoryStockUpdated $event) use ($product) {
        return $event->productId === $product->id
            && $event->changeType === 'adjusted';
    });
});

it('broadcasts stock updated when stock is reserved', function () {
    Event::fake([InventoryStockUpdated::class]);

    $product = Product::factory()->create();
    Stock::factory()->create([
        'product_id' => $product->id,
        'quantity_available' => 10,
        'quantity_reserved' => 0,
    ]);

    app(ReserveStock::class)->execute(new ReserveStockData(
        productId: $product->id,
        quantity: 2,
        orderId: 99,
    ));

    Event::assertDispatched(InventoryStockUpdated::class, function (InventoryStockUpdated $event) use ($product) {
        return $event->productId === $product->id
            && $event->changeType === 'reserved';
    });
});

it('broadcasts stock updated when stock is released', function () {
    Event::fake([InventoryStockUpdated::class]);

    $product = Product::factory()->create();
    Stock::factory()->create([
        'product_id' => $product->id,
        'quantity_available' => 5,
        'quantity_reserved' => 3,
    ]);

    app(ReleaseStockAction::class)->execute(new ReserveStockData(
        productId: $product->id,
        quantity: 2,
        orderId: 99,
        referenceId: 99,
        referenceType: 'order',
    ));

    Event::assertDispatched(InventoryStockUpdated::class, function (InventoryStockUpdated $event) use ($product) {
        return $event->productId === $product->id
            && $event->changeType === 'released';
    });
});

it('broadcasts price updated when product price changes', function () {
    Event::fake([ProductPriceUpdated::class]);

    $product = Product::factory()->create(['price_cents' => 5000]);

    app(ChangePriceAction::class)->execute(new ChangePriceData(
        productId: $product->id,
        newPriceCents: 4500,
        newSalePrice: 4000,
        changedBy: null,
        reason: 'Promotion',
    ));

    Event::assertDispatched(ProductPriceUpdated::class, function (ProductPriceUpdated $event) use ($product) {
        return $event->productId === $product->id
            && $event->newPriceCents === 4500
            && $event->newSalePrice === 4000;
    });
});

it('inventory stock updated event broadcasts on the correct channels', function () {
    $event = new InventoryStockUpdated(
        productId: 5,
        tenantId: 3,
        quantityAvailable: 10,
        quantityReserved: 2,
        changeType: 'adjusted',
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0]->name)->toBe('product.5');
    expect($channels[1]->name)->toBe('private-tenant.3.inventory');
});

it('inventory stock updated event broadcasts the correct payload', function () {
    $event = new InventoryStockUpdated(
        productId: 5,
        tenantId: 3,
        quantityAvailable: 10,
        quantityReserved: 2,
        changeType: 'adjusted',
    );

    expect($event->broadcastAs())->toBe('stock.updated');
    expect($event->broadcastWith())->toMatchArray([
        'product_id' => 5,
        'quantity_available' => 10,
        'quantity_reserved' => 2,
        'quantity_in_stock' => 8,
        'change_type' => 'adjusted',
    ]);
});
