<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cart\Actions\SendAbandonedCartRecoveryEmailsAction;
use Illuminate\Console\Command;

final class SendAbandonedCartRecoveryCommand extends Command
{
    protected $signature = 'cart:send-recovery-emails
                            {--hours=24 : Number of idle hours before a cart is considered abandoned}';

    protected $description = 'Send recovery emails to users who abandoned their carts';

    public function handle(SendAbandonedCartRecoveryEmailsAction $action): int
    {
        $hours = (int) $this->option('hours');

        $this->info("Checking for carts idle for more than {$hours} hour(s)...");

        $count = $action->execute($hours);

        $this->info("Sent {$count} abandoned cart recovery email(s).");

        return self::SUCCESS;
    }
}
