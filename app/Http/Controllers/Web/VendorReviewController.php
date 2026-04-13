<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Review\Actions\AddReviewReplyAction;
use App\Domain\Review\DTOs\StoreReviewReplyData;
use App\Domain\Review\Models\Review;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class VendorReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filter', 'unreplied');

        $reviews = Review::query()
            ->with(['product:id,name,slug', 'user', 'replies.user'])
            ->where('is_approved', true)
            ->when($filter === 'unreplied', fn ($q) => $q->doesntHave('replies'))
            ->latest()
            ->paginate(20)
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'author_name' => $r->user?->name ?? 'Anonymous',
                'created_at' => $r->created_at->toDateString(),
                'product' => [
                    'id' => $r->product->id,
                    'name' => $r->product->name,
                    'slug' => $r->product->slug,
                ],
                'replies' => $r->replies->map(fn ($reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'is_merchant_reply' => $reply->is_merchant_reply,
                    'author_name' => $reply->user?->name ?? 'Anonymous',
                    'created_at' => $reply->created_at->toDateString(),
                ]),
            ]);

        $unrepliedCount = Review::query()
            ->where('is_approved', true)
            ->doesntHave('replies')
            ->count();

        return Inertia::render('Vendor/Reviews', [
            'reviews' => $reviews,
            'filter' => $filter,
            'unreplied_count' => $unrepliedCount,
        ]);
    }

    public function reply(Request $request, Review $review, AddReviewReplyAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $action->execute(new StoreReviewReplyData(
            reviewId: $review->id,
            userId: $request->user()->id,
            body: $validated['body'],
            isMerchantReply: true,
        ));

        return back()->with('success', 'Reply posted.');
    }
}
