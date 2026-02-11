<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CrossTenantRevenueWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    protected function getStats(): array
    {
        $paidStatuses = [
            OrderStatus::Paid,
            OrderStatus::Packed,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Fulfilled,
            OrderStatus::PartiallyRefunded,
        ];

        // Platform-wide revenue - bypass tenant scoping
        $totalRevenue = Order::query()
            ->withoutGlobalScopes()
            ->whereIn('status', $paidStatuses)
            ->sum('total_cents');

        $totalRefunded = Order::query()
            ->withoutGlobalScopes()
            ->where('refunded_amount_cents', '>', 0)
            ->sum('refunded_amount_cents');

        $netRevenue = $totalRevenue - $totalRefunded;

        $todayRevenue = Order::query()
            ->withoutGlobalScopes()
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', $paidStatuses)
            ->sum('total_cents');

        $monthRevenue = Order::query()
            ->withoutGlobalScopes()
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereIn('status', $paidStatuses)
            ->sum('total_cents');

        return [
            Stat::make('Platform Revenue', '$'.number_format($totalRevenue / 100, 2))
                ->description('All-time gross revenue')
                ->color('primary')
                ->icon('heroicon-o-currency-dollar'),

            Stat::make('Net Revenue', '$'.number_format($netRevenue / 100, 2))
                ->description('After $'.number_format($totalRefunded / 100, 2).' refunded')
                ->color('success')
                ->icon('heroicon-o-banknotes'),

            Stat::make('This Month', '$'.number_format($monthRevenue / 100, 2))
                ->description(now()->format('F Y'))
                ->color('info')
                ->icon('heroicon-o-calendar'),

            Stat::make('Today', '$'.number_format($todayRevenue / 100, 2))
                ->description(now()->format('M j, Y'))
                ->color('warning')
                ->icon('heroicon-o-clock'),
        ];
    }
}
