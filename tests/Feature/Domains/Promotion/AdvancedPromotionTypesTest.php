<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Actions\CalculateDiscountAction;
use App\Domain\Promotion\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(CalculateDiscountAction::class);
    $this->product = Product::factory()->create(['price_cents' => 10000, 'is_active' => true]);
    $this->cart = Cart::factory()->create();
});

// ─── BOGO Tests ──────────────────────────────────────────────────────────────

describe('BOGO promotions', function () {
    it('gives cheapest item free for buy-2-get-1', function () {
        $item = $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 0,
            'quantity' => 3,
        ]);

        $promotion = Promotion::factory()->bogo(2, 1)->create();

        $result = $this->action->execute($this->cart, $promotion);

        // 3 units / group(3) = 1 complete group → 1 free item ($100)
        expect($result->discountCents)->toBe(10000)
            ->and($result->eligibleItemIds)->toContain($item->id);
    });

    it('gives cheapest item free with mixed prices', function () {
        $expensive = Product::factory()->create(['price_cents' => 20000, 'is_active' => true]);
        $cheap = Product::factory()->create(['price_cents' => 5000, 'is_active' => true]);

        $this->cart->items()->create([
            'product_id' => $expensive->id,
            'price_cents_snapshot' => 20000,
            'tax_cents_snapshot' => 0,
            'quantity' => 2,
        ]);
        $this->cart->items()->create([
            'product_id' => $cheap->id,
            'price_cents_snapshot' => 5000,
            'tax_cents_snapshot' => 0,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->bogo(2, 1)->create();

        $result = $this->action->execute($this->cart, $promotion);

        // 3 units → 1 free (cheapest = $50)
        expect($result->discountCents)->toBe(5000);
    });

    it('gives multiple free items for buy-2-get-2 with 4 items', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 0,
            'quantity' => 4,
        ]);

        $promotion = Promotion::factory()->bogo(2, 2)->create();

        $result = $this->action->execute($this->cart, $promotion);

        // 4 units / group(4) = 1 complete group → 2 free items = $200
        expect($result->discountCents)->toBe(20000);
    });

    it('gives no discount when not enough items for a complete group', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 0,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->bogo(2, 1)->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(0);
    });

    it('caps BOGO discount at maximum_discount_cents', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 0,
            'quantity' => 3,
        ]);

        $promotion = Promotion::factory()->bogo(2, 1)->withMaxDiscount(5000)->create();

        $result = $this->action->execute($this->cart, $promotion);

        // Free item would be $100, capped at $50
        expect($result->discountCents)->toBe(5000);
    });
});

// ─── Tiered Tests ─────────────────────────────────────────────────────────────

describe('Tiered promotions', function () {
    it('applies the highest qualifying tier', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 0,
            'quantity' => 2,
        ]);

        $promotion = Promotion::factory()->tiered([
            ['min_cents' => 5000, 'discount_bps' => 500],   // 5% from $50
            ['min_cents' => 15000, 'discount_bps' => 1000], // 10% from $150
        ])->create();

        // Subtotal = $200, qualifies for both tiers — highest threshold is $150 → 10%
        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(2000); // 10% of $200
    });

    it('applies lower tier when subtotal does not reach higher tier', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 7500,
            'tax_cents_snapshot' => 0,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->tiered([
            ['min_cents' => 5000, 'discount_bps' => 500],   // 5% from $50
            ['min_cents' => 15000, 'discount_bps' => 1000], // 10% from $150
        ])->create();

        // Subtotal = $75, only qualifies for first tier → 5% of $75 = $3.75
        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(375);
    });

    it('gives no discount when subtotal is below all tiers', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 3000,
            'tax_cents_snapshot' => 0,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->tiered([
            ['min_cents' => 5000, 'discount_bps' => 500],
        ])->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(0);
    });

    it('caps tiered discount at maximum_discount_cents', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 0,
            'quantity' => 5,
        ]);

        $promotion = Promotion::factory()->tiered([
            ['min_cents' => 5000, 'discount_bps' => 1000], // 10%
        ])->withMaxDiscount(3000)->create();

        // 10% of $500 = $50, capped at $30
        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(3000);
    });
});

// ─── Flash Sale Tests ─────────────────────────────────────────────────────────

describe('Flash sale promotions', function () {
    it('isFlashSale returns true when marked as flash sale', function () {
        $promotion = Promotion::factory()->fixed(1000)->flashSale()->create([
            'ends_at' => now()->addHours(2),
        ]);

        expect($promotion->isFlashSale())->toBeTrue();
    });

    it('isFlashSale returns false for regular promotion', function () {
        $promotion = Promotion::factory()->fixed(1000)->create();

        expect($promotion->isFlashSale())->toBeFalse();
    });

    it('timeRemainingSeconds returns positive value for active flash sale', function () {
        $promotion = Promotion::factory()->fixed(1000)->flashSale()->create([
            'ends_at' => now()->addHours(1),
        ]);

        expect($promotion->timeRemainingSeconds())->toBeGreaterThan(0);
    });

    it('timeRemainingSeconds returns 0 for expired promotion', function () {
        $promotion = Promotion::factory()->fixed(1000)->flashSale()->expired()->create();

        expect($promotion->timeRemainingSeconds())->toBe(0);
    });

    it('flash sale discount calculates normally via CalculateDiscountAction', function () {
        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'price_cents_snapshot' => 10000,
            'tax_cents_snapshot' => 0,
            'quantity' => 1,
        ]);

        $promotion = Promotion::factory()->percentage(1000)->flashSale()->create();

        $result = $this->action->execute($this->cart, $promotion);

        expect($result->discountCents)->toBe(1000); // 10% of $100
    });
});
