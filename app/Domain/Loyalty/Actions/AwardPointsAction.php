<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Loyalty\DTOs\AwardPointsData;
use App\Domain\Loyalty\Enums\TransactionType;
use App\Domain\Loyalty\Events\PointsEarned;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Shared\Domain\AuditLog;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

final readonly class AwardPointsAction
{
    /**
     * Award loyalty points to a user.
     */
    public function execute(AwardPointsData $data): LoyaltyTransaction
    {
        return DB::transaction(function () use ($data): LoyaltyTransaction {
            $account = LoyaltyAccount::query()->firstOrCreate(
                [
                    'tenant_id' => Context::get('tenant_id'),
                    'user_id' => $data->userId,
                ],
                [
                    'points_balance' => 0,
                    'total_points_earned' => 0,
                    'total_points_redeemed' => 0,
                ]
            );

            $account->increment('points_balance', $data->points);
            $account->increment('total_points_earned', $data->points);
            $account->refresh();

            $transaction = LoyaltyTransaction::create([
                'tenant_id' => $account->tenant_id,
                'user_id' => $data->userId,
                'loyalty_account_id' => $account->id,
                'type' => TransactionType::Earned,
                'points' => $data->points,
                'balance_after' => $account->points_balance,
                'description' => $data->description,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
            ]);

            AuditLog::log(
                'loyalty.points_awarded',
                LoyaltyAccount::class,
                $account->id,
                [
                    'user_id' => $data->userId,
                    'points' => $data->points,
                    'balance_after' => $account->points_balance,
                    'description' => $data->description,
                ]
            );

            PointsEarned::dispatch($data->userId, $data->points, $account->points_balance);

            return $transaction;
        });
    }
}
