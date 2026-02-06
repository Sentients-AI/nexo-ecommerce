<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Actions\FindBestPromotionAction;
use App\Domain\Promotion\Exceptions\PromotionNotApplicableException;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionUsage;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $this->product = Product::factory()->create(['price_cents' => 10000]);
    $this->cart->items()->create([
        'product_id' => $this->product->id,
        'price_cents_snapshot' => 10000,
        'tax_cents_snapshot' => 1000,
        'quantity' => 1,
    ]);
    $this->action = app(FindBestPromotionAction::class);
});

describe('Code-based Promotions', function () {
    it('finds promotion by code', function () {
        $promotion = Promotion::factory()->fixed(2000)->create([
            'code' => 'SAVE20',
        ]);

        $result = $this->action->execute($this->cart, $this->user, 'SAVE20');

        expect($result)->not->toBeNull();
        expect($result['promotion']->id)->toBe($promotion->id);
        expect($result['result']->discountCents)->toBe(2000);
    });

    it('throws exception for invalid code', function () {
        $this->action->execute($this->cart, $this->user, 'INVALID');
    })->throws(PromotionNotApplicableException::class);

    it('throws exception for expired promotion code', function () {
        Promotion::factory()->fixed(2000)->expired()->create([
            'code' => 'EXPIRED',
        ]);

        $this->action->execute($this->cart, $this->user, 'EXPIRED');
    })->throws(PromotionNotApplicableException::class);

    it('throws exception when user exceeds per-user limit', function () {
        $promotion = Promotion::factory()
            ->fixed(2000)
            ->withPerUserLimit(1)
            ->create(['code' => 'ONCE']);

        // Create existing usage for this user
        PromotionUsage::factory()->create([
            'promotion_id' => $promotion->id,
            'user_id' => $this->user->id,
            'discount_cents' => 2000,
        ]);

        $this->action->execute($this->cart, $this->user, 'ONCE');
    })->throws(PromotionNotApplicableException::class);
});

describe('Auto-apply Promotions', function () {
    it('finds best auto-apply promotion', function () {
        // Create two auto-apply promotions
        $smallPromotion = Promotion::factory()->fixed(500)->autoApply()->create();
        $largePromotion = Promotion::factory()->fixed(2000)->autoApply()->create();

        $result = $this->action->execute($this->cart, $this->user);

        expect($result)->not->toBeNull();
        expect($result['promotion']->id)->toBe($largePromotion->id);
        expect($result['result']->discountCents)->toBe(2000);
    });

    it('returns null when no auto-apply promotions exist', function () {
        // Create only code-based promotion
        Promotion::factory()->fixed(2000)->create(['code' => 'CODE123']);

        $result = $this->action->execute($this->cart, $this->user);

        expect($result)->toBeNull();
    });

    it('ignores inactive auto-apply promotions', function () {
        Promotion::factory()->fixed(2000)->autoApply()->inactive()->create();
        $activePromotion = Promotion::factory()->fixed(500)->autoApply()->create();

        $result = $this->action->execute($this->cart, $this->user);

        expect($result)->not->toBeNull();
        expect($result['promotion']->id)->toBe($activePromotion->id);
    });

    it('ignores expired auto-apply promotions', function () {
        Promotion::factory()->fixed(2000)->autoApply()->expired()->create();

        $result = $this->action->execute($this->cart, $this->user);

        expect($result)->toBeNull();
    });

    it('ignores auto-apply promotions at usage limit', function () {
        Promotion::factory()->fixed(2000)->autoApply()->atUsageLimit()->create();

        $result = $this->action->execute($this->cart, $this->user);

        expect($result)->toBeNull();
    });
});

describe('Best Promotion Selection', function () {
    it('selects percentage promotion when it gives better discount', function () {
        // Cart total is $100
        // Fixed $5 vs 10% ($10)
        $fixedPromotion = Promotion::factory()->fixed(500)->autoApply()->create();
        $percentPromotion = Promotion::factory()->percentage(1000)->autoApply()->create();

        $result = $this->action->execute($this->cart, $this->user);

        expect($result['promotion']->id)->toBe($percentPromotion->id);
        expect($result['result']->discountCents)->toBe(1000);
    });

    it('selects fixed promotion when it gives better discount', function () {
        // Cart total is $100
        // Fixed $15 vs 10% ($10)
        $fixedPromotion = Promotion::factory()->fixed(1500)->autoApply()->create();
        $percentPromotion = Promotion::factory()->percentage(1000)->autoApply()->create();

        $result = $this->action->execute($this->cart, $this->user);

        expect($result['promotion']->id)->toBe($fixedPromotion->id);
        expect($result['result']->discountCents)->toBe(1500);
    });
});

describe('Minimum Order Requirements', function () {
    it('skips auto-apply promotions that do not meet minimum order', function () {
        // Cart is $100, promotion requires $200 minimum
        $highMinPromotion = Promotion::factory()
            ->fixed(5000)
            ->autoApply()
            ->withMinimumOrder(20000)
            ->create();

        $lowMinPromotion = Promotion::factory()
            ->fixed(1000)
            ->autoApply()
            ->withMinimumOrder(5000)
            ->create();

        $result = $this->action->execute($this->cart, $this->user);

        expect($result['promotion']->id)->toBe($lowMinPromotion->id);
    });

    it('throws exception when code promotion does not meet minimum', function () {
        Promotion::factory()
            ->fixed(5000)
            ->withMinimumOrder(20000)
            ->create(['code' => 'HIGHMIN']);

        $this->action->execute($this->cart, $this->user, 'HIGHMIN');
    })->throws(PromotionNotApplicableException::class);
});
