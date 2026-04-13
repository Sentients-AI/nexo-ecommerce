<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\GiftCard\Actions\ValidateGiftCardAction;
use App\Domain\GiftCard\Exceptions\GiftCardException;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class GiftCardController extends Controller
{
    public function __construct(
        private readonly ValidateGiftCardAction $validateGiftCard,
    ) {}

    /**
     * Preview a gift card code — validate and return the available balance.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        try {
            $giftCard = $this->validateGiftCard->execute($request->input('code'));

            return response()->json([
                'valid' => true,
                'balance_cents' => $giftCard->balance_cents,
            ]);

        } catch (GiftCardException $e) {
            return response()->json([
                'valid' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => [
                    'code' => ErrorCode::InternalError->value,
                    'message' => 'An error occurred while validating the gift card.',
                    'retryable' => ErrorCode::InternalError->isRetryable(),
                ],
            ], ErrorCode::InternalError->httpStatus());
        }
    }
}
