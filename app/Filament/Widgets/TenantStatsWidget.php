<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TenantStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    protected function getStats(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $inactiveTenants = Tenant::where('is_active', false)->count();

        $totalUsers = User::query()
            ->withoutGlobalScopes()
            ->whereNotNull('tenant_id')
            ->count();

        $newTenantsThisMonth = Tenant::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return [
            Stat::make('Total Tenants', $totalTenants)
                ->description("{$newTenantsThisMonth} new this month")
                ->color('primary')
                ->icon('heroicon-o-building-office-2'),

            Stat::make('Active Tenants', $activeTenants)
                ->description(number_format($totalTenants > 0 ? ($activeTenants / $totalTenants) * 100 : 0, 1).'% of total')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Inactive Tenants', $inactiveTenants)
                ->description('Disabled or suspended')
                ->color($inactiveTenants > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-pause-circle'),

            Stat::make('Total Users', $totalUsers)
                ->description('Across all tenants')
                ->color('info')
                ->icon('heroicon-o-users'),
        ];
    }
}
