<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use Filament\Widgets\ChartWidget;

final class RevenueTrendWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Monthly Revenue Trend (12 months)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $paidStatuses = [
            OrderStatus::Paid,
            OrderStatus::Packed,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Fulfilled,
            OrderStatus::PartiallyRefunded,
        ];

        $months = collect(range(11, 0))->map(function (int $monthsAgo) use ($paidStatuses): array {
            $date = now()->subMonths($monthsAgo);

            $revenue = Order::query()
                ->withoutTenancy()
                ->whereIn('status', $paidStatuses)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total_cents');

            return [
                'label' => $date->format('M Y'),
                'revenue' => round($revenue / 100, 2),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $months->pluck('revenue')->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $months->pluck('label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
