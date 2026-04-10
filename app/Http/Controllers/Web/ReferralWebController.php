<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Domain\Referral\Actions\GenerateReferralCodeAction;
use App\Domain\Referral\DTOs\GenerateReferralCodeData;
use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Referral\Models\ReferralUsage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReferralWebController extends Controller
{
    public function __construct(
        private readonly GenerateReferralCodeAction $generateReferralCodeAction,
    ) {}

    /**
     * Show the authenticated user's referral dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $data = new GenerateReferralCodeData(
            userId: $user->id,
            referrerRewardPoints: (int) config('referral.default_referrer_reward_points', 500),
            refereeDiscountPercent: (int) config('referral.default_referee_discount_percent', 10),
            maxUses: config('referral.default_max_uses') !== null ? (int) config('referral.default_max_uses') : null,
            expiresAt: now()->addDays((int) config('referral.default_validity_days', 30)),
        );

        $referralCode = $this->generateReferralCodeAction->execute($data);

        $usages = ReferralUsage::query()
            ->where('referrer_user_id', $user->id)
            ->with('referee')
            ->orderByDesc('created_at')
            ->get();

        $totalPointsEarned = (int) LoyaltyTransaction::query()
            ->where('user_id', $user->id)
            ->where('reference_type', 'referral_usages')
            ->sum('points');

        return Inertia::render('Referral/Index', [
            'referralCode' => [
                'code' => $referralCode->code,
                'shareable_url' => $referralCode->generateShareableUrl(),
                'status' => $referralCode->status()->value,
                'referrer_reward_points' => $referralCode->referrer_reward_points,
                'referee_discount_percent' => $referralCode->referee_discount_percent,
                'max_uses' => $referralCode->max_uses,
                'used_count' => $referralCode->used_count,
                'expires_at' => $referralCode->expires_at,
                'is_active' => $referralCode->is_active,
            ],
            'stats' => [
                'total_usages' => $usages->count(),
                'total_points_earned' => $totalPointsEarned,
            ],
            'usages' => $usages->map(fn (ReferralUsage $usage): array => [
                'referee_email' => $this->maskEmail($usage->referee?->email ?? ''),
                'points_awarded' => $usage->referrer_points_awarded,
                'discount_given' => $usage->referee_discount_percent,
                'used_at' => $usage->created_at?->toIso8601String(),
            ])->values()->toArray(),
        ]);
    }

    /**
     * Show the referral landing page for a given code.
     */
    public function show(string $code): Response|RedirectResponse
    {
        $referralCode = ReferralCode::query()
            ->withoutTenancy()
            ->where('code', $code)
            ->first();

        if ($referralCode === null || ! $referralCode->isValid()) {
            return redirect('/')->with('flash', [
                'type' => 'error',
                'message' => 'This referral link is no longer valid.',
            ]);
        }

        return Inertia::render('Referral/Show', [
            'referralCode' => [
                'code' => $referralCode->code,
                'referee_discount_percent' => $referralCode->referee_discount_percent,
                'expires_at' => $referralCode->expires_at,
            ],
        ]);
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
