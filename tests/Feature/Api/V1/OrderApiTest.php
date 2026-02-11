<?php

declare(strict_types=1);

use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('Order List API', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/orders');

        $response->assertUnauthorized();
    });

    it('returns only orders belonging to authenticated user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Order::factory()->count(3)->create(['user_id' => $user->id]);
        Order::factory()->count(2)->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(3);
    });

    it('returns orders in descending order by creation date', function () {
        $user = User::factory()->create();

        $oldOrder = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);
        $newOrder = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertSuccessful();
        $orderIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($orderIds[0])->toBe($newOrder->id);
        expect($orderIds[1])->toBe($oldOrder->id);
    });

    it('returns empty array when user has no orders', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertSuccessful();
        expect($response->json('data'))->toBeEmpty();
    });

    it('paginates orders', function () {
        $user = User::factory()->create();
        Order::factory()->count(25)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(20);
        expect($response->json('meta.last_page'))->toBe(2);
    });

    it('returns orders with correct structure', function () {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'status',
                    'subtotal_cents',
                    'tax_cents',
                    'total_cents',
                    'currency',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    });
});

describe('Order Show API', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/orders/1');

        $response->assertUnauthorized();
    });

    it('returns 404 for non-existent order', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders/99999');

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'ORDER_NOT_FOUND');
    });

    it('rejects viewing order not owned by user', function () {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertForbidden();
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    });

    it('returns order details for owner', function () {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertSuccessful();
        $response->assertJsonPath('order.id', $order->id);
        $response->assertJsonPath('order.order_number', $order->order_number);
    });

    it('returns order with items', function () {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 500,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'order' => [
                'id',
                'order_number',
                'items' => [
                    '*' => [
                        'id',
                        'product_id',
                        'quantity',
                        'price_cents',
                        'name_snapshot',
                    ],
                ],
            ],
        ]);
    });
});
