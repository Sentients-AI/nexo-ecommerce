<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AdvancedAnalyticsStatsWidget;
use App\Filament\Widgets\RevenueTrendWidget;
use App\Filament\Widgets\TopProductsWidget;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class AdvancedAnalytics extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string $routePath = '/advanced-analytics';

    protected static ?string $title = 'Advanced Analytics';

    protected static ?string $navigationLabel = 'Advanced Analytics';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true || $user?->isSuperAdmin() === true;
    }

    public function getWidgets(): array
    {
        return [
            AdvancedAnalyticsStatsWidget::class,
            RevenueTrendWidget::class,
            TopProductsWidget::class,
        ];
    }

    public function getColumns(): int
    {
        return 3;
    }
}
