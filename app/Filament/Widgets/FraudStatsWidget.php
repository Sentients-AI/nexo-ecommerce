<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\User\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FraudStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $lookback = now()->subDays(config('fraud.lookback_days', 7));
        $attemptsThreshold = (int) config('fraud.payment_attempts_threshold', 3);
        $highValueThreshold = (int) config('fraud.high_value_threshold_cents', 50000);
        $newUserDays = (int) config('fraud.new_user_days', 7);
        $velocityOrders = (int) config('fraud.velocity_orders', 3);
        $velocityWindowHours = (int) config('fraud.velocity_window_hours', 1);
        $highRefundRate = (float) config('fraud.high_refund_rate', 0.5);

        $multipleAttempts = PaymentIntent::query()
            ->where('attempts', '>=', $attemptsThreshold)
            ->where('created_at', '>=', $lookback)
            ->count();

        $highValueNewUsers = Order::query()
            ->where('created_at', '>=', $lookback)
            ->where('total_cents', '>=', $highValueThreshold)
            ->whereHas('user', fn ($q) => $q->where('created_at', '>=', now()->subDays($newUserDays)))
            ->count();

        $velocityAlerts = User::query()
            ->whereHas('orders', function ($q) use ($velocityOrders, $velocityWindowHours): void {
                $q->where('created_at', '>=', now()->subHours($velocityWindowHours))
                    ->havingRaw('COUNT(*) >= ?', [$velocityOrders]);
            }, '>=', 1)
            ->count();

        $highRefundUsers = User::query()
            ->has('orders', '>=', 2)
            ->whereRaw(
                '(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.refunded_amount_cents > 0) / (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) >= ?',
                [$highRefundRate]
            )
            ->count();

        return [
            Stat::make('Multiple Payment Attempts', $multipleAttempts)
                ->description("Payments with {$attemptsThreshold}+ attempts (last {$lookback->diffInDays()} days)")
                ->color($multipleAttempts > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-credit-card'),

            Stat::make('High-Value, New-User Orders', $highValueNewUsers)
                ->description('Orders ≥ '.number_format($highValueThreshold / 100, 2)." from users <{$newUserDays} days old")
                ->color($highValueNewUsers > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Order Velocity Alerts', $velocityAlerts)
                ->description("{$velocityOrders}+ orders in {$velocityWindowHours}h window")
                ->color($velocityAlerts > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-bolt'),

            Stat::make('High Refund Rate Users', $highRefundUsers)
                ->description('>'.($highRefundRate * 100).'% of orders refunded')
                ->color($highRefundUsers > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-arrow-uturn-left'),
        ];
    }
}
