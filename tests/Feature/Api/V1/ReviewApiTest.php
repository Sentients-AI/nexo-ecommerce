<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
