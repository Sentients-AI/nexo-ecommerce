<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewPhoto;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    $this->product = Product::factory()->create(['is_active' => true]);
});

describe('Review List API', function () {
    it('returns paginated approved reviews for a product', function () {
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            Review::factory()->create([
                'product_id' => $this->product->id,
                'user_id' => $user->id,
                'is_approved' => true,
            ]);
        }
        // One unapproved review that should not appear
        Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => User::factory()->create()->id,
            'is_approved' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->slug}/reviews");

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'product_id', 'user_id', 'user_name', 'rating', 'title', 'body', 'created_at'],
                ],
            ]);
    });

    it('returns empty data when product has no approved reviews', function () {
        Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => User::factory()->create()->id,
            'is_approved' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->slug}/reviews");

        $response->assertSuccessful()
            ->assertJsonCount(0, 'data');
    });

    it('returns reviews ordered by newest first', function () {
        $older = Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => User::factory()->create()->id,
            'is_approved' => true,
            'created_at' => now()->subDays(2),
        ]);
        $newer = Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => User::factory()->create()->id,
            'is_approved' => true,
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->slug}/reviews");

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data[0]['id'])->toBe($newer->id)
            ->and($data[1]['id'])->toBe($older->id);
    });

    it('returns 404 for non-existent product', function () {
        $response = $this->getJson('/api/v1/products/non-existent-slug/reviews');

        $response->assertNotFound();
    });
});

describe('Submit Review API', function () {
    it('requires authentication', function () {
        $response = $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 5,
            'title' => 'Great product',
            'body' => 'This product is amazing!',
        ]);

        $response->assertUnauthorized();
    });

    it('creates a review for authenticated user', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 5,
            'title' => 'Great product',
            'body' => 'This product is amazing and works perfectly!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.title', 'Great product')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $this->product->id,
            'user_id' => $user->id,
            'rating' => 5,
        ]);
    });

    it('prevents duplicate reviews from the same user', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Review::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 3,
            'title' => 'Another review',
            'body' => 'Trying to review again.',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'REVIEW_ALREADY_SUBMITTED');
    });

    it('validates required fields', function () {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/v1/products/{$this->product->slug}/reviews", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating', 'title', 'body']);
    });

    it('validates rating range', function () {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 6,
            'title' => 'Test review',
            'body' => 'Test body content here.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    });

    it('validates rating is at least 1', function () {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 0,
            'title' => 'Test review',
            'body' => 'Test body content here.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    });

    it('validates title max length', function () {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/v1/products/{$this->product->slug}/reviews", [
            'rating' => 4,
            'title' => str_repeat('a', 256),
            'body' => 'Valid body text.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    });
});

describe('reply creation', function () {
    it('allows authenticated user to reply to a review', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        $review = Review::factory()->forTenant($this->tenant)->create(['is_approved' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/reviews/{$review->id}/replies", ['body' => 'Great review!']);

        $response->assertCreated();
        expect($response->json('data.body'))->toBe('Great review!')
            ->and($response->json('data.is_merchant_reply'))->toBeFalse();
        $this->assertDatabaseHas('review_replies', ['review_id' => $review->id, 'body' => 'Great review!']);
    });

    it('validates reply body is required', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        $review = Review::factory()->forTenant($this->tenant)->create(['is_approved' => true]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/reviews/{$review->id}/replies", [])->assertUnprocessable();
    });

    it('requires authentication to reply', function () {
        $review = Review::factory()->forTenant($this->tenant)->create(['is_approved' => true]);

        $this->postJson("/api/v1/reviews/{$review->id}/replies", ['body' => 'test'])->assertUnauthorized();
    });
});

describe('voting', function () {
    it('allows user to vote a review helpful', function () {
        $voter = User::factory()->forTenant($this->tenant)->create();
        $reviewer = User::factory()->forTenant($this->tenant)->create();
        $review = Review::factory()->forTenant($this->tenant)->create(['is_approved' => true, 'user_id' => $reviewer->id]);
        Sanctum::actingAs($voter);

        $response = $this->postJson("/api/v1/reviews/{$review->id}/vote", ['is_helpful' => true]);

        $response->assertOk();
        expect($response->json('data.helpful_count'))->toBe(1)
            ->and($response->json('data.user_vote'))->toBeTrue();
        $this->assertDatabaseHas('review_votes', ['review_id' => $review->id, 'user_id' => $voter->id, 'is_helpful' => true]);
    });

    it('prevents voting on own review', function () {
        $owner = User::factory()->forTenant($this->tenant)->create();
        $review = Review::factory()->forTenant($this->tenant)->create(['user_id' => $owner->id, 'is_approved' => true]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/reviews/{$review->id}/vote", ['is_helpful' => true])->assertUnprocessable();
    });

    it('updates vote when user changes their vote', function () {
        $voter = User::factory()->forTenant($this->tenant)->create();
        $reviewer = User::factory()->forTenant($this->tenant)->create();
        $review = Review::factory()->forTenant($this->tenant)->create(['is_approved' => true, 'user_id' => $reviewer->id]);
        Sanctum::actingAs($voter);

        $this->postJson("/api/v1/reviews/{$review->id}/vote", ['is_helpful' => true]);
        $response = $this->postJson("/api/v1/reviews/{$review->id}/vote", ['is_helpful' => false]);

        $response->assertOk();
        expect($response->json('data.helpful_count'))->toBe(0)
            ->and($response->json('data.user_vote'))->toBeFalse();
    });

    it('requires authentication to vote', function () {
        $review = Review::factory()->forTenant($this->tenant)->create(['is_approved' => true]);

        $this->postJson("/api/v1/reviews/{$review->id}/vote", ['is_helpful' => true])->assertUnauthorized();
    });
});

describe('review photos', function () {
    it('allows uploading photos with a review', function () {
        Storage::fake('public');
        $user = User::factory()->forTenant($this->tenant)->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->forTenant($this->tenant)->create(['is_active' => true]);

        $response = $this->postJson("/api/v1/products/{$product->slug}/reviews", [
            'rating' => 5,
            'title' => 'Great product',
            'body' => 'Really loved it, highly recommend.',
            'photos' => [
                UploadedFile::fake()->image('photo1.jpg', 800, 600),
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('review_photos', 1);
        Storage::disk('public')->assertExists(ReviewPhoto::first()->path);
    });

    it('rejects more than 5 photos', function () {
        $user = User::factory()->forTenant($this->tenant)->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->forTenant($this->tenant)->create(['is_active' => true]);

        $photos = array_fill(0, 6, UploadedFile::fake()->image('photo.jpg'));

        $this->postJson("/api/v1/products/{$product->slug}/reviews", [
            'rating' => 5,
            'title' => 'test',
            'body' => 'test body here',
            'photos' => $photos,
        ])->assertUnprocessable();
    });
});
