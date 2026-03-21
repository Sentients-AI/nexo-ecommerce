<?php

declare(strict_types=1);

namespace App\Domain\Referral\Actions;

use App\Domain\Referral\DTOs\GenerateReferralCodeData;
use App\Domain\Referral\Models\ReferralCode;
use App\Shared\Domain\AuditLog;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final readonly class GenerateReferralCodeAction
{
    /**
     * Generate a referral code for a user, or return the existing active one.
     */
    public function execute(GenerateReferralCodeData $data): ReferralCode
    {
        $existing = ReferralCode::query()
            ->where('user_id', $data->userId)
            ->where('is_active', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $code = $this->generateUniqueCode();

        $referralCode = ReferralCode::create([
            'tenant_id' => Context::get('tenant_id'),
            'user_id' => $data->userId,
            'code' => $code,
            'referrer_reward_points' => $data->referrerRewardPoints,
            'referee_discount_percent' => $data->refereeDiscountPercent,
            'max_uses' => $data->maxUses,
            'used_count' => 0,
            'expires_at' => $data->expiresAt,
            'is_active' => true,
        ]);

        AuditLog::log(
            'referral.code_generated',
            ReferralCode::class,
            $referralCode->id,
            [
                'user_id' => $data->userId,
                'code' => $code,
            ]
        );

        return $referralCode;
    }

    /**
     * Generate a unique 12-character uppercase alphanumeric code within the current tenant.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(12));
            $exists = ReferralCode::query()
                ->withoutTenancy()
                ->where('tenant_id', Context::get('tenant_id'))
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }
}
