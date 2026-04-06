<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Product\Models\Product;
use App\Domain\Question\Actions\SubmitAnswerAction;
use App\Domain\Question\Actions\SubmitQuestionAction;
use App\Domain\Question\Models\Question;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class QuestionController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        $questions = Question::query()
            ->where('product_id', $product->id)
            ->with(['user', 'answers.user'])
            ->latest()
            ->paginate(10);

        return \App\Http\Resources\Api\V1\QuestionResource::collection($questions);
    }

    public function store(Request $request, Product $product, SubmitQuestionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $question = $action->execute($product, $request->user(), $validated['body']);
        $question->load(['user', 'answers']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\QuestionResource($question),
        ], 201);
    }

    public function storeAnswer(Request $request, Question $question, SubmitAnswerAction $action): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $user = $request->user();
        $isVendorAnswer = $user->isAdmin() || $user->isSuperAdmin();

        $answer = $action->execute($question, $user, $validated['body'], $isVendorAnswer);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\QuestionAnswerResource($answer),
        ], 201);
    }
}
