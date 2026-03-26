<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Product\Models\Product;
use App\Domain\Review\Actions\AddReviewReplyAction;
use App\Domain\Review\Actions\SubmitReviewAction;
use App\Domain\Review\Actions\VoteReviewAction;
use App\Domain\Review\DTOs\ReviewData;
use App\Domain\Review\DTOs\StoreReviewReplyData;
use App\Domain\Review\DTOs\VoteReviewData;
use App\Domain\Review\Models\Review;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReviewReplyRequest;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Requests\Api\V1\VoteReviewRequest;
use App\Http\Resources\Api\V1\ReviewReplyResource;
use App\Http\Resources\Api\V1\ReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReviewController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        $reviews = Review::query()
            ->where('product_id', $product->id)
            ->approved()
            ->with(['user', 'photos', 'replies.user', 'votes'])
            ->withCount([
                'votes as helpful_votes_count' => fn ($q) => $q->where('is_helpful', true),
                'votes as not_helpful_votes_count' => fn ($q) => $q->where('is_helpful', false),
            ])
            ->orderByDesc('created_at')
            ->paginate(10);

        return ReviewResource::collection($reviews);
    }

    public function store(StoreReviewRequest $request, Product $product, SubmitReviewAction $action): JsonResponse
    {
        $result = $action->execute(new ReviewData(
            productId: $product->id,
            userId: $request->user()->id,
            rating: (int) $request->validated('rating'),
            title: $request->validated('title'),
            body: $request->validated('body'),
            photos: $request->file('photos', []),
        ));

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $result->load(['user', 'photos', 'replies.user', 'votes']);

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($result),
        ], 201);
    }

    public function storeReply(StoreReviewReplyRequest $request, Review $review, AddReviewReplyAction $action): JsonResponse
    {
        $user = $request->user();
        $isMerchantReply = $user->isAdmin() || $user->isSuperAdmin();

        $reply = $action->execute(new StoreReviewReplyData(
            reviewId: $review->id,
            userId: $user->id,
            body: $request->validated('body'),
            isMerchantReply: $isMerchantReply,
        ));

        $reply->load('user');

        return response()->json([
            'success' => true,
            'data' => new ReviewReplyResource($reply),
        ], 201);
    }

    public function vote(VoteReviewRequest $request, Review $review, VoteReviewAction $action): JsonResponse
    {
        if ($review->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => ErrorCode::ReviewVoteNotAllowed->value,
                    'message' => 'You cannot vote on your own review.',
                    'retryable' => false,
                ],
            ], ErrorCode::ReviewVoteNotAllowed->httpStatus());
        }

        $action->execute(new VoteReviewData(
            reviewId: $review->id,
            userId: $request->user()->id,
            isHelpful: (bool) $request->validated('is_helpful'),
        ));

        $review->load('votes');

        return response()->json([
            'success' => true,
            'data' => [
                'helpful_count' => $review->votes->where('is_helpful', true)->count(),
                'not_helpful_count' => $review->votes->where('is_helpful', false)->count(),
                'user_vote' => $review->votes->firstWhere('user_id', $request->user()->id)?->is_helpful,
            ],
        ]);
    }
}
