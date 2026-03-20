<?php

declare(strict_types=1);

namespace App\Domain\Referral\Actions;

use App\Domain\Loyalty\Actions\AwardPointsAction;
use App\Domain\Loyalty\DTOs\AwardPointsData;
use App\Domain\Referral\DTOs\ApplyReferralCodeData;
use App\Domain\Referral\Enums\ReferralStatus;
use App\Domain\Referral\Events\ReferralCodeUsed;
use App\Domain\Referral\Exceptions\ReferralAlreadyUsedException;
use App\Domain\Referral\Exceptions\ReferralCodeExhaustedException;
use App\Domain\Referral\Exceptions\ReferralCodeExpiredException;
use App\Domain\Referral\Exceptions\ReferralCodeInvalidException;
use App\Domain\Referral\Exceptions\SelfReferralException;
use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Referral\Models\ReferralUsage;
use App\Shared\Domain\AuditLog;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ApplyReferralCodeAction
{
    public function __construct(
        private AwardPointsAction $awardPointsAction,
    ) {}

    /**
     * Apply a referral code, rewarding both the referrer and referee.
     */
    public function execute(ApplyReferralCodeData $data): ReferralUsage
    {
        $referralCode = ReferralCode::query()
            ->where('code', $data->code)
            ->first();

        if ($referralCode === null) {
            throw new ReferralCodeInvalidException;
        }

        $status = $referralCode->status();

        if ($status === ReferralStatus::Expired) {
            throw new ReferralCodeExpiredException;
        }

        if ($status === ReferralStatus::Exhausted) {
            throw new ReferralCodeExhaustedException;
        }

        if (! $referralCode->isValid()) {
            throw new ReferralCodeInvalidException;
        }

        if ($referralCode->user_id === $data->refereeUserId) {
            throw new SelfReferralException;
        }

        $alreadyUsed = ReferralUsage::query()
            ->where('referral_code_id', $referralCode->id)
            ->where('referee_user_id', $data->refereeUserId)
            ->exists();

        if ($alreadyUsed) {
            throw new ReferralAlreadyUsedException;
        }

        return DB::transaction(function () use ($data, $referralCode): ReferralUsage {
            $couponCode = 'REF-'.Str::upper(Str::random(8));

            $usage = ReferralUsage::create([
                'tenant_id' => Context::get('tenant_id'),
                'referral_code_id' => $referralCode->id,
                'referrer_user_id' => $referralCode->user_id,
                'referee_user_id' => $data->refereeUserId,
                'referrer_points_awarded' => $referralCode->referrer_reward_points,
                'referee_discount_percent' => $referralCode->referee_discount_percent,
                'referee_coupon_code' => $couponCode,
            ]);

            $referralCode->increment('used_count');

            $this->awardPointsAction->execute(new AwardPointsData(
                userId: $referralCode->user_id,
                points: $referralCode->referrer_reward_points,
                description: 'Referral bonus - code used',
                referenceType: 'referral_usages',
                referenceId: $usage->id,
            ));

            AuditLog::log(
                'referral.code_applied',
                ReferralUsage::class,
                $usage->id,
                [
                    'referral_code_id' => $referralCode->id,
                    'code' => $data->code,
                    'referrer_user_id' => $referralCode->user_id,
                    'referee_user_id' => $data->refereeUserId,
                    'points_awarded' => $referralCode->referrer_reward_points,
                    'discount_percent' => $referralCode->referee_discount_percent,
                ]
            );

            ReferralCodeUsed::dispatch(
                $referralCode->id,
                $referralCode->user_id,
                $data->refereeUserId,
                $referralCode->referrer_reward_points,
                $referralCode->referee_discount_percent,
            );

            return $usage;
        });
    }
}
