<?php

declare(strict_types=1);

use App\Domain\GiftCard\Actions\CreateGiftCardAction;
use App\Domain\GiftCard\Actions\RedeemGiftCardAction;
use App\Domain\GiftCard\Actions\ValidateGiftCardAction;
use App\Domain\GiftCard\Exceptions\GiftCardException;
use App\Domain\GiftCard\Models\GiftCard;
use App\Domain\GiftCard\Models\GiftCardRedemption;
use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

// ── CreateGiftCardAction ─────────────────────────────────────────────────────

describe('CreateGiftCardAction', function (): void {
    it('creates a gift card with the given balance', function (): void {
        $card = app(CreateGiftCardAction::class)->execute(initialBalanceCents: 5000);

        expect($card->initial_balance_cents)->toBe(5000)
            ->and($card->balance_cents)->toBe(5000)
            ->and($card->is_active)->toBeTrue()
            ->and(mb_strlen((string) $card->code))->toBe(10);
    });

    it('uses a provided code', function (): void {
        $card = app(CreateGiftCardAction::class)->execute(
            initialBalanceCents: 1000,
            code: 'WELCOME10',
        );

        expect($card->code)->toBe('WELCOME10');
    });

    it('uppercases the code', function (): void {
        $card = app(CreateGiftCardAction::class)->execute(
            initialBalanceCents: 1000,
            code: 'lowercase',
        );

        expect($card->code)->toBe('LOWERCASE');
    });
});

// ── ValidateGiftCardAction ───────────────────────────────────────────────────

describe('ValidateGiftCardAction', function (): void {
    it('returns a valid gift card', function (): void {
        $card = GiftCard::factory()->create(['code' => 'VALID1234AB', 'balance_cents' => 5000]);

        $result = app(ValidateGiftCardAction::class)->execute('valid1234ab');

        expect($result->id)->toBe($card->id);
    });

    it('throws for an unknown code', function (): void {
        expect(fn () => app(ValidateGiftCardAction::class)->execute('BADCODE'))
            ->toThrow(GiftCardException::class);
    });

    it('throws for an inactive card', function (): void {
        GiftCard::factory()->inactive()->create(['code' => 'INACTIVE01']);

        expect(fn () => app(ValidateGiftCardAction::class)->execute('INACTIVE01'))
            ->toThrow(GiftCardException::class);
    });

    it('throws for an expired card', function (): void {
        GiftCard::factory()->expired()->create(['code' => 'EXPIRED001']);

        expect(fn () => app(ValidateGiftCardAction::class)->execute('EXPIRED001'))
            ->toThrow(GiftCardException::class);
    });

    it('throws for a depleted card', function (): void {
        GiftCard::factory()->depleted()->create(['code' => 'DEPLETED01']);

        expect(fn () => app(ValidateGiftCardAction::class)->execute('DEPLETED01'))
            ->toThrow(GiftCardException::class);
    });
});

// ── RedeemGiftCardAction ─────────────────────────────────────────────────────

describe('RedeemGiftCardAction', function (): void {
    it('deducts balance and creates a redemption record', function (): void {
        $card = GiftCard::factory()->create(['balance_cents' => 10000]);
        $order = Order::factory()->create();

        app(RedeemGiftCardAction::class)->execute($card, $order, 3000);

        expect($card->fresh()->balance_cents)->toBe(7000)
            ->and(GiftCardRedemption::query()->where('gift_card_id', $card->id)->count())->toBe(1)
            ->and(GiftCardRedemption::query()->where('gift_card_id', $card->id)->first()->amount_cents)->toBe(3000);
    });
});

// ── Gift Card Preview API ────────────────────────────────────────────────────

describe('Gift card preview endpoint', function (): void {
    it('returns valid=true for a valid gift card', function (): void {
        GiftCard::factory()->create(['code' => 'TESTCARD01', 'balance_cents' => 5000]);

        $this->postJson('/api/v1/gift-cards/preview', ['code' => 'testcard01'])
            ->assertOk()
            ->assertJson(['valid' => true, 'balance_cents' => 5000]);
    });

    it('returns valid=false for an invalid code', function (): void {
        $this->postJson('/api/v1/gift-cards/preview', ['code' => 'DOESNOTEXIST'])
            ->assertOk()
            ->assertJson(['valid' => false]);
    });

    it('returns valid=false for an expired card', function (): void {
        GiftCard::factory()->expired()->create(['code' => 'EXPIREDCARD']);

        $this->postJson('/api/v1/gift-cards/preview', ['code' => 'EXPIREDCARD'])
            ->assertOk()
            ->assertJson(['valid' => false]);
    });

    it('requires a code field', function (): void {
        $this->postJson('/api/v1/gift-cards/preview', [])
            ->assertUnprocessable();
    });
});
