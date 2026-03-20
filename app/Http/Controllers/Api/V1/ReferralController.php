<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Domain\Referral\Actions\ApplyReferralCodeAction;
use App\Domain\Referral\Actions\GenerateReferralCodeAction;
use App\Domain\Referral\DTOs\ApplyReferralCodeData;
use App\Domain\Referral\DTOs\GenerateReferralCodeData;
use App\Domain\Referral\Exceptions\ReferralAlreadyUsedException;
use App\Domain\Referral\Exceptions\ReferralCodeExhaustedException;
use App\Domain\Referral\Exceptions\ReferralCodeExpiredException;
use App\Domain\Referral\Exceptions\ReferralCodeInvalidException;
use App\Domain\Referral\Exceptions\SelfReferralException;
use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Referral\Models\ReferralUsage;
use App\Domain\Shared\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplyReferralCodeRequest;
use App\Http\Requests\Api\V1\RegenerateReferralCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class ReferralController extends Controller
{
    public function __construct(
        private readonly GenerateReferralCodeAction $generateReferralCodeAction,
        private readonly ApplyReferralCodeAction $applyReferralCodeAction,
    ) {}

    /**
     * Get the current user's referral code, auto-generating one if none exists.
     */
    public function show(Request $request): JsonResponse
    {
        $data = new GenerateReferralCodeData(
            userId: $request->user()->id,
            referrerRewardPoints: (int) config('referral.default_referrer_reward_points', 500),
            refereeDiscountPercent: (int) config('referral.default_referee_discount_percent', 10),
            maxUses: config('referral.default_max_uses') !== null ? (int) config('referral.default_max_uses') : null,
            expiresAt: now()->addDays((int) config('referral.default_validity_days', 30)),
        );

        $referralCode = $this->generateReferralCodeAction->execute($data);

        return response()->json([
            'referral_code' => $this->formatReferralCode($referralCode),
        ]);
    }

    /**
     * Get referral statistics for the current user.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $usages = ReferralUsage::query()
            ->where('referrer_user_id', $userId)
            ->with('referee')
            ->orderByDesc('created_at')
            ->get();

        $totalPointsEarned = LoyaltyTransaction::query()
            ->where('user_id', $userId)
            ->where('reference_type', 'referral_usages')
            ->sum('points');

        return response()->json([
            'stats' => [
                'total_usages' => $usages->count(),
                'total_points_earned_from_referrals' => (int) $totalPointsEarned,
                'usages' => $usages->map(fn (ReferralUsage $usage): array => [
                    'referee_email' => $this->maskEmail($usage->referee?->email ?? ''),
                    'points_awarded' => $usage->referrer_points_awarded,
                    'discount_given' => $usage->referee_discount_percent,
                    'used_at' => $usage->created_at,
                ]),
            ],
        ]);
    }

    /**
     * Apply a referral code for the current user.
     */
    public function apply(ApplyReferralCodeRequest $request): JsonResponse
    {
        try {
            $usage = $this->applyReferralCodeAction->execute(new ApplyReferralCodeData(
                code: $request->validated('code'),
                refereeUserId: $request->user()->id,
            ));

            return response()->json([
                'message' => 'Referral code applied successfully.',
                'usage' => [
                    'referrer_points_awarded' => $usage->referrer_points_awarded,
                    'referee_discount_percent' => $usage->referee_discount_percent,
                    'referee_coupon_code' => $usage->referee_coupon_code,
                ],
            ], 201);

        } catch (ReferralCodeExpiredException $e) {
            return $this->errorResponse(ErrorCode::ReferralCodeExpired, $e->getMessage());
        } catch (ReferralCodeExhaustedException $e) {
            return $this->errorResponse(ErrorCode::ReferralCodeExhausted, $e->getMessage());
        } catch (ReferralCodeInvalidException $e) {
            return $this->errorResponse(ErrorCode::ReferralCodeInvalid, $e->getMessage());
        } catch (SelfReferralException $e) {
            return $this->errorResponse(ErrorCode::SelfReferral, $e->getMessage());
        } catch (ReferralAlreadyUsedException $e) {
            return $this->errorResponse(ErrorCode::ReferralAlreadyUsed, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(ErrorCode::InternalError, 'An error occurred while applying the referral code.');
        }
    }

    /**
     * Deactivate the current referral code and generate a fresh one.
     */
    public function regenerate(RegenerateReferralCodeRequest $request): JsonResponse
    {
        ReferralCode::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $referralCode = $this->generateReferralCodeAction->execute(new GenerateReferralCodeData(
            userId: $request->user()->id,
            referrerRewardPoints: (int) config('referral.default_referrer_reward_points', 500),
            refereeDiscountPercent: (int) config('referral.default_referee_discount_percent', 10),
            maxUses: config('referral.default_max_uses') !== null ? (int) config('referral.default_max_uses') : null,
            expiresAt: now()->addDays((int) config('referral.default_validity_days', 30)),
        ));

        return response()->json([
            'message' => 'Referral code regenerated successfully.',
            'referral_code' => $this->formatReferralCode($referralCode),
        ]);
    }

    /**
     * Format a referral code for JSON response.
     *
     * @return array<string, mixed>
     */
    private function formatReferralCode(ReferralCode $referralCode): array
    {
        return [
            'code' => $referralCode->code,
            'shareable_url' => $referralCode->generateShareableUrl(),
            'status' => $referralCode->status()->value,
            'referrer_reward_points' => $referralCode->referrer_reward_points,
            'referee_discount_percent' => $referralCode->referee_discount_percent,
            'max_uses' => $referralCode->max_uses,
            'used_count' => $referralCode->used_count,
            'expires_at' => $referralCode->expires_at,
            'is_active' => $referralCode->is_active,
        ];
    }

    /**
     * Mask an email address for privacy.
     */
    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $masked = mb_substr($local, 0, 1).'***';

        return $masked.'@'.$domain;
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
