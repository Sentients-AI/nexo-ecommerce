<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

// ── Index ─────────────────────────────────────────────────────────────────────

describe('GET /vendor/reviews', function (): void {
    it('returns the reviews page', function (): void {
        $this->get('/vendor/reviews')->assertOk()->assertInertia(
            fn ($page) => $page->component('Vendor/Reviews')
        );
    });

    it('lists approved reviews', function (): void {
        Review::factory()->create(['rating' => 5, 'is_approved' => true]);

        $this->get('/vendor/reviews?filter=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Reviews')
                ->has('reviews.data', 1)
            );
    });

    it('does not list unapproved reviews', function (): void {
        Review::factory()->unapproved()->create();

        $this->get('/vendor/reviews?filter=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviews.data', 0));
    });

    it('filters to unreplied reviews only', function (): void {
        $replied = Review::factory()->create(['is_approved' => true]);
        ReviewReply::factory()->create(['review_id' => $replied->id, 'tenant_id' => $replied->tenant_id]);

        Review::factory()->create(['is_approved' => true, 'product_id' => Product::factory()]); // unreplied, different product

        $this->get('/vendor/reviews?filter=unreplied')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviews.data', 1));
    });

    it('returns unreplied_count', function (): void {
        foreach (range(1, 3) as $_) {
            Review::factory()->create(['is_approved' => true, 'product_id' => Product::factory()]);
        }
        $replied = Review::factory()->create(['is_approved' => true, 'product_id' => Product::factory()]);
        ReviewReply::factory()->create(['review_id' => $replied->id, 'tenant_id' => $replied->tenant_id]);

        $this->get('/vendor/reviews?filter=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('unreplied_count', 3));
    });
});

// ── Reply ─────────────────────────────────────────────────────────────────────

describe('POST /vendor/reviews/{review}/reply', function (): void {
    it('creates a merchant reply', function (): void {
        $review = Review::factory()->create(['is_approved' => true]);

        $this->post("/vendor/reviews/{$review->id}/reply", [
            'body' => 'Thank you for your feedback!',
        ])->assertRedirect();

        expect(ReviewReply::query()->where('review_id', $review->id)->count())->toBe(1)
            ->and(ReviewReply::query()->where('review_id', $review->id)->first()->is_merchant_reply)->toBeTrue();
    });

    it('validates body is required', function (): void {
        $review = Review::factory()->create(['is_approved' => true]);

        $this->post("/vendor/reviews/{$review->id}/reply", [])
            ->assertSessionHasErrors('body');
    });

    it('validates body minimum length', function (): void {
        $review = Review::factory()->create(['is_approved' => true]);

        $this->post("/vendor/reviews/{$review->id}/reply", ['body' => 'Hi'])
            ->assertSessionHasErrors('body');
    });
});
