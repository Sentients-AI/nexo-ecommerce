<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Review\DTOs\ReviewData;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewPhoto;
use App\Domain\Shared\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

final class SubmitReviewAction
{
    public function execute(ReviewData $data): Review|JsonResponse
    {
        $exists = Review::query()
            ->where('product_id', $data->productId)
            ->where('user_id', $data->userId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => ErrorCode::ReviewAlreadySubmitted->value,
                    'message' => 'You have already reviewed this product.',
                    'retryable' => false,
                ],
            ], ErrorCode::ReviewAlreadySubmitted->httpStatus());
        }

        $review = Review::create([
            'product_id' => $data->productId,
            'user_id' => $data->userId,
            'rating' => $data->rating,
            'title' => $data->title,
            'body' => $data->body,
        ]);

        foreach ($data->photos as $index => $file) {
            $path = $file->store('review-photos', 'public');
            ReviewPhoto::create([
                'review_id' => $review->id,
                'path' => $path,
                'disk' => 'public',
                'order' => $index,
            ]);
        }

        return $review;
    }
}
