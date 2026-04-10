<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Loyalty\Models\LoyaltyTransaction;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Referral\Models\ReferralUsage;
use App\Domain\User\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AdvancedAnalyticsStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

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

        $totalOrders = Order::query()->withoutTenancy()->whereIn('status', $paidStatuses)->count();
        $totalRevenueCents = (int) Order::query()->withoutTenancy()->whereIn('status', $paidStatuses)->sum('total_cents');
        $avgOrderValueCents = $totalOrders > 0 ? (int) round($totalRevenueCents / $totalOrders) : 0;

        $totalRefundedCents = (int) Order::query()->withoutTenancy()->sum('refunded_amount_cents');
        $refundRate = $totalRevenueCents > 0
            ? round(($totalRefundedCents / $totalRevenueCents) * 100, 1)
            : 0.0;

        $newUsersThisMonth = User::query()
            ->withoutTenancy()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $newUsersLastMonth = User::query()
            ->withoutTenancy()
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        $userGrowthPercent = $newUsersLastMonth > 0
            ? round((($newUsersThisMonth - $newUsersLastMonth) / $newUsersLastMonth) * 100, 1)
            : 0.0;

        $totalReferrals = ReferralUsage::query()->withoutTenancy()->count();

        $totalLoyaltyPointsIssued = (int) LoyaltyTransaction::query()
            ->withoutTenancy()
            ->where('points', '>', 0)
            ->sum('points');

        return [
            Stat::make('Avg. Order Value', '$'.number_format($avgOrderValueCents / 100, 2))
                ->description('Across all paid orders')
                ->color('primary')
                ->icon('heroicon-o-shopping-cart'),

            Stat::make('Refund Rate', $refundRate.'%')
                ->description('Refunded / gross revenue')
                ->color($refundRate > 5 ? 'danger' : 'success')
                ->icon('heroicon-o-receipt-refund'),

            Stat::make('New Users This Month', number_format($newUsersThisMonth))
                ->description(($userGrowthPercent >= 0 ? '+' : '').$userGrowthPercent.'% vs last month')
                ->color($userGrowthPercent >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-users'),

            Stat::make('Total Referrals', number_format($totalReferrals))
                ->description('Successful referral signups')
                ->color('info')
                ->icon('heroicon-o-user-plus'),

            Stat::make('Loyalty Points Issued', number_format($totalLoyaltyPointsIssued))
                ->description('All-time points awarded')
                ->color('warning')
                ->icon('heroicon-o-star'),
        ];
    }
}
