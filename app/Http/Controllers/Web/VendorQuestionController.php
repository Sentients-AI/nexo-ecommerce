<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Question\Actions\SubmitAnswerAction;
use App\Domain\Question\Models\Question;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VendorQuestionController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filter', 'unanswered');

        $questions = Question::query()
            ->with(['product:id,name,slug', 'user', 'answers.user'])
            ->when($filter === 'unanswered', fn ($q) => $q->unanswered())
            ->latest()
            ->paginate(20)
            ->through(fn (Question $q) => [
                'id' => $q->id,
                'body' => $q->body,
                'is_answered' => $q->is_answered,
                'author_name' => $q->user?->name ?? 'Anonymous',
                'created_at' => $q->created_at->toDateString(),
                'product' => [
                    'id' => $q->product->id,
                    'name' => $q->product->name,
                    'slug' => $q->product->slug,
                ],
                'answers' => $q->answers->map(fn ($a) => [
                    'id' => $a->id,
                    'body' => $a->body,
                    'is_vendor_answer' => $a->is_vendor_answer,
                    'author_name' => $a->user?->name ?? 'Anonymous',
                    'created_at' => $a->created_at->toDateString(),
                ]),
            ]);

        $unansweredCount = Question::query()->unanswered()->count();

        return Inertia::render('Vendor/Questions', [
            'questions' => $questions,
            'filter' => $filter,
            'unanswered_count' => $unansweredCount,
        ]);
    }

    public function answer(Request $request, Question $question, SubmitAnswerAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $user = $request->user();
        $isVendorAnswer = $user->isAdmin() || $user->isSuperAdmin();

        $action->execute($question, $user, $validated['body'], $isVendorAnswer);

        return back()->with('success', 'Answer posted.');
    }
}
