<?php

declare(strict_types=1);

use App\Domain\Question\Models\Question;
use App\Domain\Question\Models\QuestionAnswer;
use App\Domain\Role\Models\Role;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function (): void {
    $this->actingAsUserInTenant();
});

describe('Vendor questions index', function (): void {
    it('renders the questions page showing unanswered questions by default', function (): void {
        Question::factory()->count(3)->create();
        Question::factory()->answered()->count(2)->create();

        $this->get('/vendor/questions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Questions')
                ->has('questions')
                ->where('filter', 'unanswered')
                ->has('unanswered_count')
            );
    });

    it('filters to show all questions when requested', function (): void {
        Question::factory()->count(2)->create();
        Question::factory()->answered()->count(3)->create();

        $this->get('/vendor/questions?filter=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filter', 'all')
                ->where('questions.total', 5)
            );
    });

    it('shows all questions when filter=answered (controller does not filter by answered)', function (): void {
        Question::factory()->count(2)->create();
        Question::factory()->answered()->count(3)->create();

        $this->get('/vendor/questions?filter=answered')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filter', 'answered')
                ->where('questions.total', 5)
            );
    });

    it('returns the correct unanswered count', function (): void {
        Question::factory()->count(4)->create();
        Question::factory()->answered()->count(1)->create();

        $this->get('/vendor/questions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unanswered_count', 4)
            );
    });

    it('redirects guests to login', function (): void {
        auth()->logout();

        $this->get('/vendor/questions')->assertRedirect();
    });
});

describe('Vendor question answer', function (): void {
    it('posts an answer to a question and marks it as answered', function (): void {
        $question = Question::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post("/vendor/questions/{$question->id}/answer", [
                'body' => 'This is the official vendor answer with enough detail.',
            ])
            ->assertRedirect();

        expect($question->fresh()->is_answered)->toBeTrue();
        $this->assertDatabaseHas(QuestionAnswer::class, [
            'question_id' => $question->id,
        ]);
    });

    it('marks the answer as a vendor answer when posted by an admin', function (): void {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin role']);
        $this->actingAsUserInTenant(['role_id' => $adminRole->id]);

        $question = Question::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post("/vendor/questions/{$question->id}/answer", [
                'body' => 'Authoritative vendor response here.',
            ]);

        $answer = QuestionAnswer::query()->where('question_id', $question->id)->first();
        expect($answer->is_vendor_answer)->toBeTrue();
    });

    it('does not mark the answer as a vendor answer for non-admin users', function (): void {
        $question = Question::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post("/vendor/questions/{$question->id}/answer", [
                'body' => 'Answer posted by a regular user without admin role.',
            ]);

        $answer = QuestionAnswer::query()->where('question_id', $question->id)->first();
        expect($answer->is_vendor_answer)->toBeFalse();
    });

    it('fails when the answer body is empty', function (): void {
        $question = Question::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post("/vendor/questions/{$question->id}/answer", ['body' => ''])
            ->assertSessionHasErrors('body');
    });

    it('fails when the answer body is too short', function (): void {
        $question = Question::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post("/vendor/questions/{$question->id}/answer", ['body' => 'Yes'])
            ->assertSessionHasErrors('body');
    });

    it('returns 404 for a non-existent question', function (): void {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/vendor/questions/99999/answer', ['body' => 'This should not work.'])
            ->assertNotFound();
    });
});
