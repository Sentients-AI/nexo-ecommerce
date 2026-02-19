<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Product\Models\Product;
use App\Domain\Review\Actions\SubmitReviewAction;
use App\Domain\Review\DTOs\ReviewData;
use App\Domain\Review\Models\Review;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReviewRequest;
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
            ->with('user')
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
        ));

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $result->load('user');

        return response()->json([
            'success' => true,
            'data' => new ReviewResource($result),
        ], 201);
    }
}
