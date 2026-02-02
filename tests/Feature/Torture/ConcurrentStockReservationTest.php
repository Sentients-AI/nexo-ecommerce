<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Actions\ReserveStockAction;
use App\Domain\Inventory\DTOs\ReserveStockData;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Order\Actions\CreateOrderFromCart;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Concurrent Stock Reservation', function () {
    it('prevents over-reservation of limited stock', function () {
        $product = Product::factory()->create();
        $initialAvailable = 10;
        $stock = Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => $initialAvailable,
            'quantity_reserved' => 0,
        ]);

        $action = app(ReserveStockAction::class);
        $successCount = 0;
        $failCount = 0;
        $reserveAmount = 3;

        // Try to reserve more than available
        for ($i = 0; $i < 5; $i++) {
            try {
                $action->execute(new ReserveStockData(
                    productId: $product->id,
                    quantity: $reserveAmount,
                    orderId: $i + 1
                ));
                $successCount++;
            } catch (InsufficientStockException) {
                $failCount++;
            }
        }

        $stock->refresh();

        // INVARIANT: Total reserved should never exceed initial available
        $totalReserved = $stock->quantity_reserved;
        expect($totalReserved)->toBeLessThanOrEqual($initialAvailable)
            ->and($stock->quantity_available)->toBeGreaterThanOrEqual(0)
            ->and($totalReserved)->toBe($successCount * $reserveAmount)
            ->and($successCount + $failCount)->toBe(5);
    });

    it('enforces atomicity of stock reservation', function () {
        $product = Product::factory()->create();
        $stock = Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 3,
            'quantity_reserved' => 0,
        ]);

        // Create multiple carts wanting the same limited stock
        $carts = collect();
        for ($i = 0; $i < 5; $i++) {
            $carts->push(Cart::factory()->withProduct($product->id, 2)->create());
        }

        $action = app(CreateOrderFromCart::class);
        $successCount = 0;
        $failCount = 0;

        foreach ($carts as $cart) {
            try {
                $action->execute(new CreateOrderData(
                    userId: (int) $cart->user_id,
                    cartId: (string) $cart->id,
                    currency: 'USD'
                ));
                $successCount++;
            } catch (Throwable) {
                $failCount++;
            }
        }

        $stock->refresh();

        // Only 1 order should succeed (requesting 2 units from 3 available)
        expect($successCount)->toBe(1)
            ->and($failCount)->toBe(4)
            ->and(Order::query()->count())->toBe(1)
            ->and($stock->quantity_available)->toBe(1)
            ->and($stock->quantity_reserved)->toBe(2);
    });

    it('invariant: stock never goes negative', function () {
        $product = Product::factory()->create();
        $stock = Stock::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 1,
            'quantity_reserved' => 0,
        ]);

        $action = app(ReserveStockAction::class);

        // Try to reserve more than available
        for ($i = 0; $i < 10; $i++) {
            try {
                $action->execute(new ReserveStockData(
                    productId: $product->id,
                    quantity: 1,
                    orderId: $i + 1
                ));
            } catch (InsufficientStockException) {
                // Expected for most attempts
            }
        }

        $stock->refresh();

        // Invariant check: stock should never be negative
        expect($stock->quantity_available)->toBeGreaterThanOrEqual(0)
            ->and($stock->quantity_reserved)->toBeGreaterThanOrEqual(0)
            ->and($stock->quantity_available)->toBe(0)
            ->and($stock->quantity_reserved)->toBe(1);
    });

    it('multi-product reservation is atomic', function () {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        Stock::factory()->create([
            'product_id' => $product1->id,
            'quantity_available' => 5,
            'quantity_reserved' => 0,
        ]);
        Stock::factory()->create([
            'product_id' => $product2->id,
            'quantity_available' => 0, // No stock for product 2
            'quantity_reserved' => 0,
        ]);

        // Cart has items from both products
        $cart = Cart::factory()->create();
        $cart->items()->create([
            'product_id' => $product1->id,
            'quantity' => 1,
            'price_cents_snapshot' => 1000,
            'tax_cents_snapshot' => 100,
        ]);
        $cart->items()->create([
            'product_id' => $product2->id,
            'quantity' => 1,
            'price_cents_snapshot' => 1000,
            'tax_cents_snapshot' => 100,
        ]);

        $action = app(CreateOrderFromCart::class);

        // Should fail because product2 has no stock
        expect(fn () => $action->execute(new CreateOrderData(
            userId: (int) $cart->user_id,
            cartId: (string) $cart->id,
            currency: 'USD'
        )))->toThrow(InsufficientStockException::class);

        // Verify no partial reservation occurred
        $stock1 = Stock::query()->where('product_id', $product1->id)->first();
        expect($stock1->quantity_available)->toBe(5)
            ->and($stock1->quantity_reserved)->toBe(0)
            ->and(Order::query()->count())->toBe(0);
    });
});
