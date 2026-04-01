<?php

declare(strict_types=1);

use App\Domain\Inventory\Events\StockReplenished;
use App\Domain\Inventory\Listeners\NotifyWaitlistSubscribers;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Inventory\Models\WaitlistSubscription;
use App\Domain\Product\Models\Product;
use App\Notifications\BackInStockNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('POST /api/v1/products/{slug}/waitlist', function () {
    it('subscribes an email to a waitlist for an out-of-stock product', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 0]);

        $this->postJson("/api/v1/products/{$product->slug}/waitlist", ['email' => 'test@example.com'])
            ->assertCreated()
            ->assertJson(['message' => 'You will be notified when this product is back in stock.']);

        expect(WaitlistSubscription::query()->where('email', 'test@example.com')->exists())->toBeTrue();
    });

    it('is idempotent — subscribing twice creates only one record', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 0]);

        $this->postJson("/api/v1/products/{$product->slug}/waitlist", ['email' => 'test@example.com'])
            ->assertCreated();

        $this->postJson("/api/v1/products/{$product->slug}/waitlist", ['email' => 'test@example.com'])
            ->assertCreated();

        expect(WaitlistSubscription::query()->where('email', 'test@example.com')->count())->toBe(1);
    });

    it('rejects subscription if product is already in stock', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 10, 'quantity_reserved' => 0]);

        $this->postJson("/api/v1/products/{$product->slug}/waitlist", ['email' => 'test@example.com'])
            ->assertUnprocessable()
            ->assertJson(['message' => 'Product is already in stock.']);
    });

    it('validates email is required', function () {
        $product = Product::factory()->create(['is_active' => true]);

        $this->postJson("/api/v1/products/{$product->slug}/waitlist", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates email format', function () {
        $product = Product::factory()->create(['is_active' => true]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 0]);

        $this->postJson("/api/v1/products/{$product->slug}/waitlist", ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('returns 404 for a non-existent product slug', function () {
        $this->postJson('/api/v1/products/does-not-exist/waitlist', ['email' => 'test@example.com'])
            ->assertNotFound();
    });
});

describe('StockReplenished event dispatched on inventory update', function () {
    it('dispatches StockReplenished when stock goes from zero to positive', function () {
        Event::fake([StockReplenished::class]);

        $product = Product::factory()->create();
        $stock = Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 0, 'quantity_reserved' => 0]);

        $this->actingAsUserInTenant();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 5])
            ->assertRedirect();

        Event::assertDispatched(StockReplenished::class, fn ($e) => $e->productId === $product->id);
    });

    it('does not dispatch StockReplenished when stock was already positive', function () {
        Event::fake([StockReplenished::class]);

        $product = Product::factory()->create();
        $stock = Stock::factory()->create(['product_id' => $product->id, 'quantity_available' => 3, 'quantity_reserved' => 0]);

        $this->actingAsUserInTenant();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->patch("/vendor/inventory/{$stock->id}", ['quantity_available' => 10])
            ->assertRedirect();

        Event::assertNotDispatched(StockReplenished::class);
    });
});

describe('NotifyWaitlistSubscribers listener', function () {
    it('sends BackInStockNotification to all pending waitlist subscribers', function () {
        Notification::fake();

        $product = Product::factory()->create();
        WaitlistSubscription::factory()->count(3)->create([
            'product_id' => $product->id,
            'notified_at' => null,
        ]);

        $listener = new NotifyWaitlistSubscribers;
        $listener->handle(new StockReplenished($product->id, (int) $this->tenant->id, 10));

        Notification::assertSentOnDemand(BackInStockNotification::class);
        expect(WaitlistSubscription::query()->whereNotNull('notified_at')->count())->toBe(3);
    });

    it('skips already-notified subscribers', function () {
        Notification::fake();

        $product = Product::factory()->create();
        WaitlistSubscription::factory()->notified()->count(2)->create(['product_id' => $product->id]);
        WaitlistSubscription::factory()->create(['product_id' => $product->id, 'notified_at' => null]);

        $listener = new NotifyWaitlistSubscribers;
        $listener->handle(new StockReplenished($product->id, (int) $this->tenant->id, 5));

        Notification::assertSentOnDemandTimes(BackInStockNotification::class, 1);
    });

    it('does nothing when product does not exist', function () {
        Notification::fake();

        $listener = new NotifyWaitlistSubscribers;
        $listener->handle(new StockReplenished(99999, (int) $this->tenant->id, 5));

        Notification::assertNothingSent();
    });
});
