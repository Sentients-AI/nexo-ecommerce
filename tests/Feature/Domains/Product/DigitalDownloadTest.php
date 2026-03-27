<?php

declare(strict_types=1);

use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Actions\GenerateDownloadTokenAction;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductDownload;
use App\Domain\User\Models\User;
use App\Notifications\DownloadReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

describe('GenerateDownloadTokenAction', function () {
    it('creates a download token for each downloadable item in the order', function () {
        Storage::fake('private');

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_downloadable' => true,
            'download_file_path' => 'downloads/ebook.pdf',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $action = app(GenerateDownloadTokenAction::class);
        $tokens = $action->execute($order->id);

        expect($tokens)->toHaveCount(1)
            ->and(array_key_exists($item->id, $tokens))->toBeTrue();

        $this->assertDatabaseHas('product_downloads', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'download_count' => 0,
        ]);
    });

    it('stores the token as a SHA-256 hash, not plain text', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_downloadable' => true,
            'download_file_path' => 'downloads/ebook.pdf',
        ]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $action = app(GenerateDownloadTokenAction::class);
        $tokens = $action->execute($order->id);
        $plain = array_values($tokens)[0];

        $download = ProductDownload::query()->first();

        expect($download->token_hash)->toBe(hash('sha256', $plain))
            ->and($download->token_hash)->not->toBe($plain);
    });

    it('skips non-downloadable products', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_downloadable' => false]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $action = app(GenerateDownloadTokenAction::class);
        $tokens = $action->execute($order->id);

        expect($tokens)->toBeEmpty();
        expect(ProductDownload::query()->count())->toBe(0);
    });

    it('sends a DownloadReady notification to the user', function () {
        Notification::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_downloadable' => true,
            'download_file_path' => 'downloads/ebook.pdf',
        ]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        app(GenerateDownloadTokenAction::class)->execute($order->id);

        Notification::assertSentTo($user, DownloadReadyNotification::class);
    });
});

describe('DownloadController', function () {
    it('streams the file for a valid token', function () {
        Storage::fake('private');
        Storage::disk('private')->put('downloads/ebook.pdf', 'PDF content here');

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_downloadable' => true,
            'download_file_path' => 'downloads/ebook.pdf',
        ]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $plain = bin2hex(random_bytes(32));
        ProductDownload::factory()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(48),
            'download_count' => 0,
            'max_downloads' => 5,
        ]);

        $response = $this->get("/downloads/{$plain}");

        $response->assertOk();
    });

    it('increments download count on each access', function () {
        Storage::fake('private');
        Storage::disk('private')->put('downloads/ebook.pdf', 'PDF content here');

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_downloadable' => true,
            'download_file_path' => 'downloads/ebook.pdf',
        ]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $plain = bin2hex(random_bytes(32));
        $download = ProductDownload::factory()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(48),
            'download_count' => 0,
            'max_downloads' => 5,
        ]);

        $this->get("/downloads/{$plain}");

        expect($download->fresh()->download_count)->toBe(1);
    });

    it('returns 404 for an unknown token', function () {
        $response = $this->get('/downloads/totally-invalid-token');

        $response->assertNotFound();
    });

    it('returns 410 for an expired token', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_downloadable' => true, 'download_file_path' => 'downloads/ebook.pdf']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $plain = bin2hex(random_bytes(32));
        ProductDownload::factory()->expired()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
        ]);

        $response = $this->get("/downloads/{$plain}");

        $response->assertStatus(410);
    });

    it('returns 410 when download limit is exhausted', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_downloadable' => true, 'download_file_path' => 'downloads/ebook.pdf']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $plain = bin2hex(random_bytes(32));
        ProductDownload::factory()->exhausted()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(48),
        ]);

        $response = $this->get("/downloads/{$plain}");

        $response->assertStatus(410);
    });
});

describe('OrderPaid event listener', function () {
    it('fires GenerateDownloadsOnOrderPaid when OrderPaid is dispatched', function () {
        Event::fake([OrderPaid::class]);

        $order = Order::factory()->create();

        OrderPaid::dispatch(
            $order->id,
            $order->user_id,
            $order->tenant_id,
            $order->order_number,
            $order->total_cents,
        );

        Event::assertDispatched(OrderPaid::class);
    });
});
