<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Loyalty\Actions\ExpireLoyaltyPointsAction;
use Illuminate\Console\Command;

final class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Expire loyalty points that have passed their expiry date';

    public function handle(ExpireLoyaltyPointsAction $action): int
    {
        if (config('loyalty.expiry_days') === null) {
            $this->info('Point expiry is disabled (loyalty.expiry_days is null). Skipping.');

            return self::SUCCESS;
        }

        $this->info('Expiring loyalty points...');

        $count = $action->execute();

        $this->info("Expired points for {$count} account(s).");

        return self::SUCCESS;
    }
}
