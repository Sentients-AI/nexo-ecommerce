<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Actions\CalculateDiscountAction;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $this->action = app(CalculateDiscountAction::class);
});

describe('Fixed Discount Calculation', function () {
    it('calculates fixed discount correctly', function () {
        $product = Product::factory()->create(['price_cents' => 10000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 1000,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->fixed(2000)->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(2000);
        expect($result->promotionId)->toBe($promotion->id);
    });

    it('caps fixed discount at eligible subtotal', function () {
        $product = Product::factory()->create(['price_cents' => 1000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 1000,
            'tax_cents_snapshot' => 100,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->fixed(5000)->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(1000); // Capped at subtotal
    });
});

describe('Percentage Discount Calculation', function () {
    it('calculates percentage discount correctly', function () {
        $product = Product::factory()->create(['price_cents' => 10000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 1000,
            'quantity' => 1,
        ]);

        // 1000 basis points = 10%
        $promotion = Promotion::factory()->percentage(1000)->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(1000); // 10% of 10000
    });

    it('applies maximum discount cap for percentage', function () {
        $product = Product::factory()->create(['price_cents' => 100000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 100000,
            'tax_cents_snapshot' => 10000,
            'quantity' => 1,
        ]);

        // 20% discount with $50 cap
        $promotion = Promotion::factory()
            ->percentage(2000)
            ->withMaxDiscount(5000)
            ->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(5000); // Capped at max
    });
});

describe('Scope Filtering', function () {
    it('applies to all products when scope is all', function () {
        $product1 = Product::factory()->create(['price_cents' => 5000]);
        $product2 = Product::factory()->create(['price_cents' => 3000]);

        $this->cart->items()->createMany([
            [
                'product_id' => $product1->id,
                'price_cents_snapshot' => 5000,
                'tax_cents_snapshot' => 500,
                'quantity' => 1,
            ],
            [
                'product_id' => $product2->id,
                'price_cents_snapshot' => 3000,
                'tax_cents_snapshot' => 300,
                'quantity' => 1,
            ],
        ]);

        $promotion = Promotion::factory()->percentage(1000)->create([
            'scope' => PromotionScope::All,
        ]);

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->eligibleSubtotalCents)->toBe(8000);
        expect($result->discountCents)->toBe(800); // 10% of 8000
    });

    it('only applies to specific products when scope is product', function () {
        $eligibleProduct = Product::factory()->create(['price_cents' => 5000]);
        $ineligibleProduct = Product::factory()->create(['price_cents' => 3000]);

        $this->cart->items()->createMany([
            [
                'product_id' => $eligibleProduct->id,
                'price_cents_snapshot' => 5000,
                'tax_cents_snapshot' => 500,
                'quantity' => 1,
            ],
            [
                'product_id' => $ineligibleProduct->id,
                'price_cents_snapshot' => 3000,
                'tax_cents_snapshot' => 300,
                'quantity' => 1,
            ],
        ]);

        $promotion = Promotion::factory()->percentage(1000)->forProducts()->create();
        $promotion->products()->attach($eligibleProduct->id);

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->eligibleSubtotalCents)->toBe(5000);
        expect($result->discountCents)->toBe(500); // 10% of 5000
    });

    it('only applies to products in specific categories when scope is category', function () {
        $eligibleCategory = Category::factory()->create();
        $ineligibleCategory = Category::factory()->create();

        $eligibleProduct = Product::factory()->create([
            'price_cents' => 5000,
            'category_id' => $eligibleCategory->id,
        ]);
        $ineligibleProduct = Product::factory()->create([
            'price_cents' => 3000,
            'category_id' => $ineligibleCategory->id,
        ]);

        $this->cart->items()->createMany([
            [
                'product_id' => $eligibleProduct->id,
                'price_cents_snapshot' => 5000,
                'tax_cents_snapshot' => 500,
                'quantity' => 1,
            ],
            [
                'product_id' => $ineligibleProduct->id,
                'price_cents_snapshot' => 3000,
                'tax_cents_snapshot' => 300,
                'quantity' => 1,
            ],
        ]);

        $promotion = Promotion::factory()->percentage(1000)->forCategories()->create();
        $promotion->categories()->attach($eligibleCategory->id);

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->eligibleSubtotalCents)->toBe(5000);
        expect($result->discountCents)->toBe(500); // 10% of 5000
    });
});

describe('Validation', function () {
    it('returns zero discount for inactive promotion', function () {
        $product = Product::factory()->create(['price_cents' => 10000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 1000,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->fixed(2000)->inactive()->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(0);
    });

    it('returns zero discount for expired promotion', function () {
        $product = Product::factory()->create(['price_cents' => 10000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 1000,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->fixed(2000)->expired()->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(0);
    });

    it('returns zero discount when usage limit is reached', function () {
        $product = Product::factory()->create(['price_cents' => 10000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 1000,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->fixed(2000)->atUsageLimit()->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(0);
    });

    it('returns zero discount when minimum order not met', function () {
        $product = Product::factory()->create(['price_cents' => 2000]);
        $this->cart->items()->create([
            'product_id' => $product->id,
            'price_cents_snapshot' => 2000,
            'tax_cents_snapshot' => 200,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()
            ->fixed(500)
            ->withMinimumOrder(5000)
            ->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(0);
    });
});
