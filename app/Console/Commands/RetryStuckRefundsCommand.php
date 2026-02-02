<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Refund\Actions\RetryRefundExecutionAction;
use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\Refund\Models\Refund;
use Illuminate\Console\Command;
use Throwable;

final class RetryStuckRefundsCommand extends Command
{
    protected $signature = 'refunds:retry-stuck
        {--dry-run : Show what would be done without making changes}
        {--limit=50 : Maximum number of refunds to process}
        {--age=10 : Minimum age in minutes for a refund to be considered stuck}';

    protected $description = 'Find and retry refunds stuck in approved or failed state';

    public function handle(RetryRefundExecutionAction $retryRefund): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $ageMinutes = (int) $this->option('age');

        $this->info('Scanning for stuck refunds...');

        $stuckRefunds = Refund::query()
            ->whereIn('status', [RefundStatus::Approved, RefundStatus::Failed])
            ->where(function ($query) use ($ageMinutes): void {
                $query->where('approved_at', '<', now()->subMinutes($ageMinutes))
                    ->orWhere('updated_at', '<', now()->subMinutes($ageMinutes));
            })
            ->limit($limit)
            ->get();

        if ($stuckRefunds->isEmpty()) {
            $this->info('No stuck refunds found.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d stuck refund(s):', $stuckRefunds->count()));

        $retried = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($stuckRefunds as $refund) {
            $this->line(sprintf(
                '  Refund #%d: Order #%d, Amount: %s %s, Status: %s',
                $refund->id,
                $refund->order_id,
                number_format($refund->amount_cents / 100, 2),
                $refund->currency,
                $refund->status->value
            ));

            if ($dryRun) {
                $this->comment('    [DRY RUN] Would retry execution');

                continue;
            }

            try {
                $retryRefund->execute($refund);
                $this->info('    [SUCCESS] Refund processed successfully');
                $succeeded++;
            } catch (Throwable $e) {
                $this->error("    [FAILED] {$e->getMessage()}");
                $failed++;
            }

            $retried++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->info(sprintf('Dry run complete. %d refund(s) would be retried.', $stuckRefunds->count()));
        } else {
            $this->info(sprintf(
                'Retry complete. Retried: %d, Succeeded: %d, Failed: %d',
                $retried,
                $succeeded,
                $failed
            ));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
