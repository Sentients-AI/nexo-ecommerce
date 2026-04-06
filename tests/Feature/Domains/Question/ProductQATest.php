<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Question\Actions\SubmitAnswerAction;
use App\Domain\Question\Actions\SubmitQuestionAction;
use App\Domain\Question\Models\Question;
use App\Domain\Question\Models\QuestionAnswer;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
});

// ── Actions ──────────────────────────────────────────────────────────────────

describe('SubmitQuestionAction', function () {
    it('creates a question for a product', function () {
        $product = Product::factory()->create();
        $user = $this->actingAsUserInTenant();

        $action = app(SubmitQuestionAction::class);
        $question = $action->execute($product, $user, 'Does this come in red?');

        expect($question->body)->toBe('Does this come in red?')
            ->and($question->product_id)->toBe($product->id)
            ->and($question->user_id)->toBe($user->id)
            ->and($question->is_answered)->toBeFalse();
    });
});

describe('SubmitAnswerAction', function () {
    it('creates an answer and marks the question as answered', function () {
        $product = Product::factory()->create();
        $asker = $this->actingAsUserInTenant();
        $answerer = $this->actingAsUserInTenant();

        $question = Question::factory()->forTenant($this->tenant)->create([
            'product_id' => $product->id,
            'user_id' => $asker->id,
        ]);

        $action = app(SubmitAnswerAction::class);
        $answer = $action->execute($question, $answerer, 'Yes, it comes in red.', false);

        expect($answer->body)->toBe('Yes, it comes in red.')
            ->and($answer->is_vendor_answer)->toBeFalse()
            ->and($question->fresh()->is_answered)->toBeTrue();
    });

    it('marks answer as vendor answer for admin users', function () {
        $product = Product::factory()->create();
        $asker = $this->actingAsUserInTenant();
        $admin = $this->actingAsUserInTenant();

        $question = Question::factory()->forTenant($this->tenant)->create([
            'product_id' => $product->id,
            'user_id' => $asker->id,
        ]);

        $action = app(SubmitAnswerAction::class);
        $answer = $action->execute($question, $admin, 'Vendor reply.', true);

        expect($answer->is_vendor_answer)->toBeTrue();
    });

    it('does not flip is_answered back to false on a second answer', function () {
        $product = Product::factory()->create();
        $question = Question::factory()->answered()->forTenant($this->tenant)->create(['product_id' => $product->id]);
        $user = $this->actingAsUserInTenant();

        $action = app(SubmitAnswerAction::class);
        $action->execute($question, $user, 'Another answer.', false);

        expect($question->fresh()->is_answered)->toBeTrue();
    });
});

// ── API: GET questions ───────────────────────────────────────────────────────

describe('GET /api/v1/products/{slug}/questions', function () {
    it('returns paginated questions with answers', function () {
        $product = Product::factory()->create(['is_active' => true]);
        $user = $this->actingAsUserInTenant();
        $question = Question::factory()->forTenant($this->tenant)->create(['product_id' => $product->id, 'user_id' => $user->id]);
        $question->answers()->create(['user_id' => $user->id, 'body' => 'Answer body', 'is_vendor_answer' => false]);

        $this->getJson("/api/v1/products/{$product->slug}/questions")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'body', 'is_answered', 'author_name', 'answers']]])
            ->assertJsonCount(1, 'data');
    });

    it('returns empty when no questions exist', function () {
        $product = Product::factory()->create(['is_active' => true]);

        $this->getJson("/api/v1/products/{$product->slug}/questions")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

// ── API: POST question ───────────────────────────────────────────────────────

describe('POST /api/v1/products/{slug}/questions', function () {
    it('lets an authenticated user ask a question', function () {
        $product = Product::factory()->create(['is_active' => true]);
        $user = $this->actingAsUserInTenant();

        $this->actingAs($user)
            ->postJson("/api/v1/products/{$product->slug}/questions", ['body' => 'Is this waterproof?'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Is this waterproof?');

        expect(Question::query()->where('product_id', $product->id)->exists())->toBeTrue();
    });

    it('rejects unauthenticated users', function () {
        $product = Product::factory()->create(['is_active' => true]);

        $this->postJson("/api/v1/products/{$product->slug}/questions", ['body' => 'Is this waterproof?'])
            ->assertUnauthorized();
    });

    it('validates body is required and at least 10 chars', function () {
        $product = Product::factory()->create(['is_active' => true]);
        $user = $this->actingAsUserInTenant();

        $this->actingAs($user)
            ->postJson("/api/v1/products/{$product->slug}/questions", ['body' => 'Short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });
});

// ── API: POST answer ─────────────────────────────────────────────────────────

describe('POST /api/v1/questions/{question}/answers', function () {
    it('lets an authenticated user post an answer', function () {
        $product = Product::factory()->create();
        $asker = $this->actingAsUserInTenant();
        $answerer = $this->actingAsUserInTenant();

        $question = Question::factory()->forTenant($this->tenant)->create([
            'product_id' => $product->id,
            'user_id' => $asker->id,
        ]);

        $this->actingAs($answerer)
            ->postJson("/api/v1/questions/{$question->id}/answers", ['body' => 'Yes it is waterproof.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Yes it is waterproof.');

        expect(QuestionAnswer::query()->where('question_id', $question->id)->exists())->toBeTrue();
    });

    it('rejects unauthenticated users', function () {
        $product = Product::factory()->create();
        $user = User::factory()->forTenant($this->tenant)->create();
        $question = Question::factory()->forTenant($this->tenant)->create(['product_id' => $product->id, 'user_id' => $user->id]);

        $this->postJson("/api/v1/questions/{$question->id}/answers", ['body' => 'Yes it is.'])
            ->assertUnauthorized();
    });
});

// ── Vendor: GET /vendor/questions ────────────────────────────────────────────

describe('GET /vendor/questions', function () {
    it('renders the vendor questions page', function () {
        $this->actingAsUserInTenant();

        $this->get(route('vendor.questions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Questions')
                ->has('questions')
                ->has('unanswered_count')
            );
    });

    it('redirects guests to login', function () {
        $this->get(route('vendor.questions.index'))->assertRedirect();
    });
});

// ── Vendor: POST /vendor/questions/{question}/answer ─────────────────────────

describe('POST /vendor/questions/{question}/answer', function () {
    it('lets a vendor user post an answer', function () {
        $vendor = $this->actingAsUserInTenant();
        $product = Product::factory()->create();
        $asker = $this->actingAsUserInTenant();

        $question = Question::factory()->forTenant($this->tenant)->create([
            'product_id' => $product->id,
            'user_id' => $asker->id,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($vendor)
            ->post(route('vendor.questions.answer', $question), ['body' => 'Yes, this product is waterproof.'])
            ->assertRedirect();

        expect(QuestionAnswer::query()->where('question_id', $question->id)->exists())->toBeTrue();
        expect($question->fresh()->is_answered)->toBeTrue();
    });
});
