<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use App\Filament\Widgets\FlaggedOrdersWidget;
use App\Filament\Widgets\FraudStatsWidget;
use App\Filament\Widgets\SuspiciousUsersWidget;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class FraudDashboard extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string $routePath = '/fraud';

    protected static ?string $title = 'Fraud Dashboard';

    protected static ?string $navigationLabel = 'Fraud';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true || $user?->isSuperAdmin() === true;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getFlaggedCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public function getWidgets(): array
    {
        return [
            FraudStatsWidget::class,
            FlaggedOrdersWidget::class,
            SuspiciousUsersWidget::class,
        ];
    }

    public function getColumns(): int
    {
        return 2;
    }

    private static function getFlaggedCount(): int
    {
        $lookback = now()->subDays(config('fraud.lookback_days', 7));
        $attemptsThreshold = (int) config('fraud.payment_attempts_threshold', 3);
        $highValueThreshold = (int) config('fraud.high_value_threshold_cents', 50000);
        $newUserDays = (int) config('fraud.new_user_days', 7);
        $velocityOrders = (int) config('fraud.velocity_orders', 3);
        $velocityWindowHours = (int) config('fraud.velocity_window_hours', 1);
        $highRefundRate = (float) config('fraud.high_refund_rate', 0.5);

        $flaggedOrders = Order::query()
            ->where('created_at', '>=', $lookback)
            ->where(function (Builder $q) use ($attemptsThreshold, $highValueThreshold, $newUserDays): void {
                $q->whereHas('paymentIntent', fn (Builder $pi) => $pi->where('attempts', '>=', $attemptsThreshold))
                    ->orWhere(function (Builder $sub) use ($highValueThreshold, $newUserDays): void {
                        $sub->where('total_cents', '>=', $highValueThreshold)
                            ->whereHas('user', fn (Builder $u) => $u->where('created_at', '>=', now()->subDays($newUserDays)));
                    })
                    ->orWhere(function (Builder $sub) use ($highValueThreshold): void {
                        $sub->whereNotNull('guest_email')
                            ->where('total_cents', '>=', $highValueThreshold);
                    });
            })
            ->count();

        $suspiciousUsers = User::query()
            ->where(function ($q) use ($velocityOrders, $velocityWindowHours, $highRefundRate): void {
                $q->whereRaw(
                    '(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.created_at >= ?) >= ?',
                    [now()->subHours($velocityWindowHours), $velocityOrders]
                )
                    ->orWhere(function ($sub) use ($highRefundRate): void {
                        $sub->whereRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) >= 2')
                            ->whereRaw(
                                '(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND refunded_amount_cents > 0) / (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) >= ?',
                                [$highRefundRate]
                            );
                    });
            })
            ->count();

        return $flaggedOrders + $suspiciousUsers;
    }
}
