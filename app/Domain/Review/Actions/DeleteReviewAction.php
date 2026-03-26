<?php

declare(strict_types=1);

namespace App\Domain\Review\Actions;

use App\Domain\Review\Models\Review;
use Illuminate\Support\Facades\Storage;

final class DeleteReviewAction
{
    public function execute(Review $review): void
    {
        $review->load('photos');

        foreach ($review->photos as $photo) {
            Storage::disk($photo->disk)->delete($photo->path);
        }

        $review->delete();
    }
}
