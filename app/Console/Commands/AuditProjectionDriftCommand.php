<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Order\Guards\OrderPaymentRequiredGuard;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Guards\PaymentAmountGuard;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Refund\Guards\RefundAmountGuard;
use App\Domain\Refund\Guards\RefundProviderConfirmationGuard;
use App\Domain\Refund\Models\Refund;
use Illuminate\Console\Command;

final class AuditProjectionDriftCommand extends Command
{
    protected $signature = 'projections:audit
        {--fix : Attempt to fix detected issues}';

    protected $description = 'Audit projections for data integrity issues and invariant violations';

    public function handle(): int
    {
        $this->info('Starting projection audit...');

        $violations = [];

        // Check Payment Amount Guards
        $this->info('Checking payment amount invariants...');
        $violations = array_merge($violations, $this->auditPaymentAmounts());

        // Check Refund Amount Guards
        $this->info('Checking refund amount invariants...');
        $violations = array_merge($violations, $this->auditRefundAmounts());

        // Check Order Payment Required Guards
        $this->info('Checking order payment requirements...');
        $violations = array_merge($violations, $this->auditOrderPayments());

        // Check Refund Provider Confirmation Guards
        $this->info('Checking refund provider confirmations...');
        $violations = array_merge($violations, $this->auditRefundProviderConfirmations());

        // Report findings
        if (empty($violations)) {
            $this->info('No invariant violations detected.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Found '.count($violations).' invariant violations:');
        $this->newLine();

        foreach ($violations as $violation) {
            $this->warn("  [{$violation['guard']}] {$violation['entity_type']} #{$violation['entity_id']}");
            $this->line("    {$violation['message']}");
        }

        $this->newLine();

        if ($this->option('fix')) {
            $this->warn('Auto-fix is not yet implemented. Manual review required.');
        }

        return self::FAILURE;
    }

    /**
     * @return array<array{guard: string, entity_type: string, entity_id: int, message: string}>
     */
    private function auditPaymentAmounts(): array
    {
        $violations = [];

        PaymentIntent::query()
            ->with('order')
            ->chunk(100, function ($intents) use (&$violations) {
                foreach ($intents as $intent) {
                    $guard = new PaymentAmountGuard($intent);
                    if (! $guard->check()) {
                        $violations[] = [
                            'guard' => $guard->getGuardName(),
                            'entity_type' => 'PaymentIntent',
                            'entity_id' => $intent->id,
                            'message' => $guard->getViolationMessage(),
                        ];
                    }
                }
            });

        return $violations;
    }

    /**
     * @return array<array{guard: string, entity_type: string, entity_id: int, message: string}>
     */
    private function auditRefundAmounts(): array
    {
        $violations = [];

        Refund::query()
            ->with('order')
            ->chunk(100, function ($refunds) use (&$violations) {
                foreach ($refunds as $refund) {
                    $guard = new RefundAmountGuard($refund);
                    if (! $guard->check()) {
                        $violations[] = [
                            'guard' => $guard->getGuardName(),
                            'entity_type' => 'Refund',
                            'entity_id' => $refund->id,
                            'message' => $guard->getViolationMessage(),
                        ];
                    }
                }
            });

        return $violations;
    }

    /**
     * @return array<array{guard: string, entity_type: string, entity_id: int, message: string}>
     */
    private function auditOrderPayments(): array
    {
        $violations = [];

        Order::query()
            ->with('paymentIntent')
            ->where('status', 'paid')
            ->chunk(100, function ($orders) use (&$violations) {
                foreach ($orders as $order) {
                    $guard = new OrderPaymentRequiredGuard($order);
                    if (! $guard->check()) {
                        $violations[] = [
                            'guard' => $guard->getGuardName(),
                            'entity_type' => 'Order',
                            'entity_id' => $order->id,
                            'message' => $guard->getViolationMessage(),
                        ];
                    }
                }
            });

        return $violations;
    }

    /**
     * @return array<array{guard: string, entity_type: string, entity_id: int, message: string}>
     */
    private function auditRefundProviderConfirmations(): array
    {
        $violations = [];

        Refund::query()
            ->where('status', 'succeeded')
            ->chunk(100, function ($refunds) use (&$violations) {
                foreach ($refunds as $refund) {
                    $guard = new RefundProviderConfirmationGuard($refund);
                    if (! $guard->check()) {
                        $violations[] = [
                            'guard' => $guard->getGuardName(),
                            'entity_type' => 'Refund',
                            'entity_id' => $refund->id,
                            'message' => $guard->getViolationMessage(),
                        ];
                    }
                }
            });

        return $violations;
    }
}
