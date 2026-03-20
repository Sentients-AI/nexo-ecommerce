<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Loyalty\Actions\RedeemPointsAction;
use App\Domain\Loyalty\DTOs\RedeemPointsData;
use App\Domain\Loyalty\Exceptions\InsufficientPointsException;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RedeemPointsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Throwable;

final class LoyaltyController extends Controller
{
    public function __construct(
        private readonly RedeemPointsAction $redeemPointsAction,
    ) {}

    /**
     * Get the authenticated user's loyalty account.
     */
    public function index(Request $request): JsonResponse
    {
        $account = LoyaltyAccount::query()->firstOrCreate(
            [
                'tenant_id' => Context::get('tenant_id'),
                'user_id' => $request->user()->id,
            ],
            [
                'points_balance' => 0,
                'total_points_earned' => 0,
                'total_points_redeemed' => 0,
            ]
        );

        return response()->json([
            'loyalty_account' => [
                'id' => $account->id,
                'points_balance' => $account->points_balance,
                'total_points_earned' => $account->total_points_earned,
                'total_points_redeemed' => $account->total_points_redeemed,
                'points_value_cents' => $account->points_balance * config('loyalty.points_value_cents', 1),
            ],
        ]);
    }

    /**
     * Get the authenticated user's transaction history.
     */
    public function transactions(Request $request): JsonResponse
    {
        $transactions = LoyaltyTransaction::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $transactions->map(fn (LoyaltyTransaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'type_label' => $transaction->type->label(),
                'points' => $transaction->points,
                'balance_after' => $transaction->balance_after,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
            ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Redeem loyalty points.
     */
    public function redeem(RedeemPointsRequest $request): JsonResponse
    {
        try {
            $transaction = $this->redeemPointsAction->execute(new RedeemPointsData(
                userId: $request->user()->id,
                points: (int) $request->validated('points'),
                description: 'Points redeemed by user',
            ));

            return response()->json([
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type->value,
                    'points' => $transaction->points,
                    'balance_after' => $transaction->balance_after,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                ],
            ], 201);

        } catch (InsufficientPointsException $e) {
            return $this->errorResponse(ErrorCode::InsufficientPoints, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(ErrorCode::InternalError, 'An error occurred while redeeming points.');
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
