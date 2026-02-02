<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Idempotency\Actions\EnsureIdempotentAction;
use App\Domain\Idempotency\Actions\StoreIdempotencyResultAction;
use App\Domain\Idempotency\Models\IdempotencyKey;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Order\Actions\CreateOrderFromCart;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Double Checkout Prevention', function () {
    it('prevents creating duplicate order from same cart', function () {
        $stock = Stock::factory()->create([
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        $cart = Cart::factory()->withProduct($stock->product_id, 2)->create();

        $action = app(CreateOrderFromCart::class);
        $data = new CreateOrderData(
            userId: (int) $cart->user_id,
            cartId: (string) $cart->id,
            currency: 'USD'
        );

        $order = $action->execute($data);

        expect($order)->toBeInstanceOf(Order::class)
            ->and(Order::query()->count())->toBe(1);

        // Second attempt should fail because cart is now completed
        expect(fn () => $action->execute($data))
            ->toThrow(DomainException::class, 'Cannot modify a completed cart');

        expect(Order::query()->count())->toBe(1);
    });

    it('enforces idempotency key for same checkout request', function () {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->withItems(2)->create();

        $idempotencyKey = 'checkout_'.$cart->id.'_'.uniqid();
        $action = 'checkout';
        $payload = ['cart_id' => $cart->id, 'currency' => 'USD'];

        $ensureIdempotent = app(EnsureIdempotentAction::class);
        $storeIdempotency = app(StoreIdempotencyResultAction::class);

        // First request - should return null (no prior record)
        $existingResponse = $ensureIdempotent->execute(
            $idempotencyKey,
            $user->id,
            $action,
            $payload
        );

        expect($existingResponse)->toBeNull();

        // Record the response
        $storeIdempotency->execute(
            $idempotencyKey,
            $user->id,
            $action,
            $payload,
            responseCode: 200,
            responseBody: ['order_id' => 1]
        );

        // Second request with same key should return cached response
        $cachedResponse = $ensureIdempotent->execute(
            $idempotencyKey,
            $user->id,
            $action,
            $payload
        );

        expect($cachedResponse)->not->toBeNull()
            ->and($cachedResponse['order_id'])->toBe(1);
    });

    it('rejects idempotency key reuse with different payload', function () {
        $user = User::factory()->create();

        $idempotencyKey = 'checkout_reuse_test_'.uniqid();
        $action = 'checkout';
        $payload1 = ['cart_id' => 1, 'currency' => 'USD'];
        $payload2 = ['cart_id' => 2, 'currency' => 'USD'];

        $ensureIdempotent = app(EnsureIdempotentAction::class);
        $storeIdempotency = app(StoreIdempotencyResultAction::class);

        // First request
        $ensureIdempotent->execute($idempotencyKey, $user->id, $action, $payload1);

        $storeIdempotency->execute(
            $idempotencyKey,
            $user->id,
            $action,
            $payload1,
            responseCode: 200,
            responseBody: ['order_id' => 1]
        );

        // Second request with different payload should throw
        expect(fn () => $ensureIdempotent->execute($idempotencyKey, $user->id, $action, $payload2))
            ->toThrow(ConflictHttpException::class, 'Idempotency key reused with different payload');
    });

    it('allows expired idempotency keys to be reused', function () {
        $user = User::factory()->create();

        $idempotencyKey = 'checkout_expired_'.uniqid();
        $action = 'checkout';
        $payload = ['cart_id' => 1, 'currency' => 'USD'];

        // Create an expired key directly
        IdempotencyKey::query()->create([
            'key' => $idempotencyKey,
            'user_id' => $user->id,
            'action' => $action,
            'request_fingerprint' => hash('sha256', json_encode($payload)),
            'response_code' => 200,
            'response_body' => ['order_id' => 1],
            'expires_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        $ensureIdempotent = app(EnsureIdempotentAction::class);

        // Expired key should be deleted and return null
        $result = $ensureIdempotent->execute($idempotencyKey, $user->id, $action, $payload);

        expect($result)->toBeNull();
    });
});

describe('Invariant: No Duplicate Orders', function () {
    it('never creates duplicate orders even with rapid sequential requests', function () {
        $stock = Stock::factory()->create([
            'quantity_available' => 100,
            'quantity_reserved' => 0,
        ]);

        $cart = Cart::factory()->withProduct($stock->product_id, 1)->create();

        $action = app(CreateOrderFromCart::class);
        $data = new CreateOrderData(
            userId: (int) $cart->user_id,
            cartId: (string) $cart->id,
            currency: 'USD'
        );

        $successCount = 0;
        $failCount = 0;

        // Simulate rapid sequential attempts
        for ($i = 0; $i < 5; $i++) {
            try {
                $action->execute($data);
                $successCount++;
            } catch (Throwable) {
                $failCount++;
            }
        }

        expect($successCount)->toBe(1)
            ->and($failCount)->toBe(4)
            ->and(Order::query()->count())->toBe(1);
    });
});
