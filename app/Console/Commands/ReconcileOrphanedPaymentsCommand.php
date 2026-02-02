<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Order\Actions\RetryOrderFinalizationAction;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use Illuminate\Console\Command;
use Throwable;

final class ReconcileOrphanedPaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-orphaned
        {--dry-run : Show what would be done without making changes}
        {--limit=100 : Maximum number of orders to process}';

    protected $description = 'Find and fix orders where payment succeeded but order status was not updated';

    public function handle(RetryOrderFinalizationAction $retryFinalization): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('Scanning for orphaned payments...');

        $orphanedOrders = Order::query()
            ->whereIn('status', [OrderStatus::Pending])
            ->whereHas('paymentIntent', function ($query): void {
                $query->where('status', PaymentStatus::Succeeded);
            })
            ->limit($limit)
            ->get();

        if ($orphanedOrders->isEmpty()) {
            $this->info('No orphaned payments found.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d orphaned payment(s):', $orphanedOrders->count()));

        $fixed = 0;
        $failed = 0;

        foreach ($orphanedOrders as $order) {
            $paymentIntent = $order->paymentIntent;

            $this->line(sprintf(
                '  Order #%s: status=%s, payment=%s (%s)',
                $order->order_number,
                $order->status->value,
                $paymentIntent?->status->value ?? 'none',
                $paymentIntent?->provider_reference ?? 'no reference'
            ));

            if ($dryRun) {
                $this->comment('    [DRY RUN] Would update to PAID');

                continue;
            }

            try {
                $retryFinalization->execute($order);
                $this->info('    [FIXED] Updated to PAID');
                $fixed++;
            } catch (Throwable $e) {
                $this->error("    [FAILED] {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info(sprintf('Dry run complete. %d order(s) would be fixed.', $orphanedOrders->count()));
        } else {
            $this->info(sprintf('Reconciliation complete. Fixed: %d, Failed: %d', $fixed, $failed));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
