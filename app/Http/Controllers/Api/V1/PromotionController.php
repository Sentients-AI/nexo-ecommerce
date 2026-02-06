<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cart\Models\Cart;
use App\Domain\Promotion\Actions\FindBestPromotionAction;
use App\Domain\Promotion\Exceptions\PromotionNotApplicableException;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplyPromotionRequest;
use App\Http\Resources\Api\V1\PromotionResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Throwable;

final class PromotionController extends Controller
{
    public function __construct(
        private readonly FindBestPromotionAction $findBestPromotion,
    ) {}

    /**
     * Apply a promotion code to a cart.
     */
    public function apply(ApplyPromotionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cart = Cart::query()
                ->with('items.product')
                ->findOrFail($request->validated('cart_id'));

            // Verify cart belongs to user
            if ($cart->user_id !== $user?->id) {
                return $this->errorResponse(ErrorCode::Forbidden, 'You do not own this cart.');
            }

            // Find and validate the promotion
            $result = $this->findBestPromotion->execute(
                $cart,
                $user,
                $request->validated('code')
            );

            if ($result === null) {
                return $this->errorResponse(
                    ErrorCode::ValidationFailed,
                    'Promotion code is not valid or not applicable.'
                );
            }

            return response()->json([
                'promotion' => new PromotionResource($result['promotion']),
                'discount_cents' => $result['result']->discountCents,
                'eligible_subtotal_cents' => $result['result']->eligibleSubtotalCents,
                'eligible' => true,
            ]);

        } catch (PromotionNotApplicableException $e) {
            return $this->errorResponse(ErrorCode::ValidationFailed, $e->reason);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(ErrorCode::InternalError, 'An error occurred while applying the promotion.');
        }
    }

    /**
     * Get active promotions (public, for display).
     */
    public function active(): JsonResponse
    {
        $now = Carbon::now();

        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where('auto_apply', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->get();

        return response()->json([
            'promotions' => PromotionResource::collection($promotions),
        ]);
    }

    /**
     * Validate a promotion code without applying it.
     */
    public function validate(ApplyPromotionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cart = Cart::query()
                ->with('items.product')
                ->findOrFail($request->validated('cart_id'));

            if ($cart->user_id !== $user?->id) {
                return $this->errorResponse(ErrorCode::Forbidden, 'You do not own this cart.');
            }

            $result = $this->findBestPromotion->execute(
                $cart,
                $user,
                $request->validated('code')
            );

            if ($result === null) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Promotion code is not valid or not applicable.',
                ]);
            }

            return response()->json([
                'valid' => true,
                'promotion' => new PromotionResource($result['promotion']),
                'discount_cents' => $result['result']->discountCents,
            ]);

        } catch (PromotionNotApplicableException $e) {
            return response()->json([
                'valid' => false,
                'message' => $e->reason,
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(ErrorCode::InternalError, 'An error occurred while validating the promotion.');
        }
    }

    private function errorResponse(ErrorCode $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'retryable' => $code->isRetryable(),
            ],
        ], $code->httpStatus());
    }
}
