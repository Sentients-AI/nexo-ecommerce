<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Loyalty\Enums\TransactionType;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Shared\Domain\AuditLog;
use Illuminate\Support\Facades\DB;

final readonly class ExpireLoyaltyPointsAction
{
    /**
     * Expire points from loyalty transactions that have passed their expiry date.
     *
     * Uses per-transaction TTL: sums all earned transactions past expires_at,
     * subtracts already-issued expiry debits for the account, and issues a
     * single Expired debit for the remainder (capped at the current balance).
     *
     * @return int Number of accounts that had points expired.
     */
    public function execute(): int
    {
        $expiredCount = 0;

        LoyaltyAccount::query()
            ->whereHas('transactions', function ($query): void {
                $query->where('type', TransactionType::Earned->value)
                    ->where('expires_at', '<', now())
                    ->where('points', '>', 0);
            })
            ->where('points_balance', '>', 0)
            ->cursor()
            ->each(function (LoyaltyAccount $account) use (&$expiredCount): void {
                $expired = $this->expireAccount($account);
                if ($expired > 0) {
                    $expiredCount++;
                }
            });

        return $expiredCount;
    }

    private function expireAccount(LoyaltyAccount $account): int
    {
        return DB::transaction(function () use ($account): int {
            $account = LoyaltyAccount::query()
                ->where('id', $account->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($account->points_balance <= 0) {
                return 0;
            }

            $totalExpiredEarned = LoyaltyTransaction::query()
                ->where('loyalty_account_id', $account->id)
                ->where('type', TransactionType::Earned->value)
                ->where('expires_at', '<', now())
                ->sum('points');

            $alreadyDebited = LoyaltyTransaction::query()
                ->where('loyalty_account_id', $account->id)
                ->where('type', TransactionType::Expired->value)
                ->sum('points');

            $pointsToExpire = $totalExpiredEarned + $alreadyDebited;

            if ($pointsToExpire <= 0) {
                return 0;
            }

            $pointsToExpire = min($pointsToExpire, $account->points_balance);

            $account->decrement('points_balance', $pointsToExpire);
            $account->refresh();

            LoyaltyTransaction::create([
                'tenant_id' => $account->tenant_id,
                'user_id' => $account->user_id,
                'loyalty_account_id' => $account->id,
                'type' => TransactionType::Expired,
                'points' => -$pointsToExpire,
                'balance_after' => $account->points_balance,
                'description' => 'Points expired due to inactivity',
            ]);

            AuditLog::log(
                'loyalty.points_expired',
                LoyaltyAccount::class,
                $account->id,
                [
                    'user_id' => $account->user_id,
                    'points_expired' => $pointsToExpire,
                    'balance_after' => $account->points_balance,
                ]
            );

            return $pointsToExpire;
        });
    }
}
