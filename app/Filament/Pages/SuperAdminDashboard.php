<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\CrossTenantRevenueWidget;
use App\Filament\Widgets\RecentTenantActivityWidget;
use App\Filament\Widgets\TenantStatsWidget;
use App\Filament\Widgets\TopTenantsWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

final class SuperAdminDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected string $view = 'filament.pages.super-admin-dashboard';

    protected static ?string $navigationLabel = 'Platform Overview';

    protected static ?string $title = 'Platform Overview';

    protected static ?string $slug = 'super-admin';

    protected static ?int $navigationSort = -100;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TenantStatsWidget::class,
            CrossTenantRevenueWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TopTenantsWidget::class,
            RecentTenantActivityWidget::class,
        ];
    }
}
