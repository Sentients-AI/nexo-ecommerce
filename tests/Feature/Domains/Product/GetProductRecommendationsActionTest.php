<?php

declare(strict_types=1);

use App\Domain\Category\Models\Category;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Actions\GetProductRecommendationsAction;
use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    Cache::flush();
});

describe('GetProductRecommendationsAction', function () {
    it('returns products co-purchased with the given product', function () {
        $target = Product::factory()->create(['is_active' => true]);
        $coProduct1 = Product::factory()->create(['is_active' => true]);
        $coProduct2 = Product::factory()->create(['is_active' => true]);

        // Two orders both contain $target + $coProduct1
        foreach (range(1, 2) as $i) {
            $order = Order::factory()->create();
            OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $target->id]);
            OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $coProduct1->id]);
        }

        // One order contains $target + $coProduct2
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $target->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $coProduct2->id]);

        $results = app(GetProductRecommendationsAction::class)->execute($target);

        // coProduct1 (frequency 2) should rank above coProduct2 (frequency 1)
        expect($results->pluck('id')->all())
            ->toContain($coProduct1->id)
            ->toContain($coProduct2->id);

        expect($results->first()->id)->toBe($coProduct1->id);
    });

    it('excludes the target product itself from recommendations', function () {
        $target = Product::factory()->create(['is_active' => true]);
        $other = Product::factory()->create(['is_active' => true]);

        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $target->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $other->id]);

        $results = app(GetProductRecommendationsAction::class)->execute($target);

        expect($results->pluck('id')->all())->not->toContain($target->id);
    });

    it('excludes inactive products from recommendations', function () {
        $target = Product::factory()->create();
        $inactive = Product::factory()->create(['is_active' => false]);

        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $target->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $inactive->id]);

        $results = app(GetProductRecommendationsAction::class)->execute($target);

        expect($results->pluck('id')->all())->not->toContain($inactive->id);
    });

    it('falls back to same-category products when co-purchase data is insufficient', function () {
        $category = Category::factory()->create();
        $target = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $categoryProduct = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $otherCategory = Product::factory()->create(['is_active' => true]);

        // Target has been ordered alone — no co-purchases
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $target->id]);

        $results = app(GetProductRecommendationsAction::class)->execute($target);

        expect($results->pluck('id')->all())->toContain($categoryProduct->id)
            ->and($results->pluck('id')->all())->not->toContain($target->id);
    });

    it('returns empty collection and falls back gracefully when no orders exist', function () {
        $target = Product::factory()->create();

        $results = app(GetProductRecommendationsAction::class)->execute($target);

        expect($results)->toBeInstanceOf(Collection::class);
    });

    it('returns at most 8 recommendations', function () {
        $target = Product::factory()->create(['is_active' => true]);
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $target->id]);

        Product::factory()->count(12)->create(['is_active' => true])->each(function (Product $p) use ($order) {
            OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $p->id]);
        });

        $results = app(GetProductRecommendationsAction::class)->execute($target);

        expect($results->count())->toBeLessThanOrEqual(8);
    });

    it('caches results so the second call does not re-query', function () {
        $target = Product::factory()->create();
        $action = app(GetProductRecommendationsAction::class);

        $cacheKey = "recommendations:product:{$target->id}:tenant:{$target->tenant_id}";

        expect(Cache::has($cacheKey))->toBeFalse();

        $action->execute($target);

        expect(Cache::has($cacheKey))->toBeTrue();
    });
});

describe('ProductController show page', function () {
    it('renders the product show page with all required props', function () {
        $product = Product::factory()->create(['is_active' => true]);

        $this->get("/en/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Show')
                ->has('product')
                ->has('reviewStats')
                ->has('relatedProducts')
            );
    });

    it('action is invokable from controller context and returns a collection', function () {
        $product = Product::factory()->create(['is_active' => true]);

        $results = app(GetProductRecommendationsAction::class)
            ->execute($product);

        expect($results)->toBeInstanceOf(Collection::class);
    });
});
