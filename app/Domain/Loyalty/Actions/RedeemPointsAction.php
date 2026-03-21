<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Loyalty\DTOs\RedeemPointsData;
use App\Domain\Loyalty\Enums\TransactionType;
use App\Domain\Loyalty\Events\PointsRedeemed;
use App\Domain\Loyalty\Exceptions\InsufficientPointsException;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Shared\Domain\AuditLog;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

final readonly class RedeemPointsAction
{
    /**
     * Redeem loyalty points for a user.
     *
     * @throws InsufficientPointsException
     */
    public function execute(RedeemPointsData $data): LoyaltyTransaction
    {
        return DB::transaction(function () use ($data): LoyaltyTransaction {
            $account = LoyaltyAccount::query()
                ->where('tenant_id', Context::get('tenant_id'))
                ->where('user_id', $data->userId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $account->canRedeem($data->points)) {
                throw new InsufficientPointsException($account->points_balance, $data->points);
            }

            $account->decrement('points_balance', $data->points);
            $account->increment('total_points_redeemed', $data->points);
            $account->refresh();

            $transaction = LoyaltyTransaction::create([
                'tenant_id' => $account->tenant_id,
                'user_id' => $data->userId,
                'loyalty_account_id' => $account->id,
                'type' => TransactionType::Redeemed,
                'points' => -$data->points,
                'balance_after' => $account->points_balance,
                'description' => $data->description,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
            ]);

            AuditLog::log(
                'loyalty.points_redeemed',
                LoyaltyAccount::class,
                $account->id,
                [
                    'user_id' => $data->userId,
                    'points' => $data->points,
                    'balance_after' => $account->points_balance,
                    'description' => $data->description,
                ]
            );

            PointsRedeemed::dispatch($data->userId, $data->points, $account->points_balance);

            return $transaction;
        });
    }
}
