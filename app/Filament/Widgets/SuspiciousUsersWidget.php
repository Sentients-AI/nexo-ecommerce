<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\User\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class SuspiciousUsersWidget extends TableWidget
{
    protected static ?string $heading = 'Suspicious User Activity';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $velocityOrders = (int) config('fraud.velocity_orders', 3);
        $velocityWindowHours = (int) config('fraud.velocity_window_hours', 1);
        $highRefundRate = (float) config('fraud.high_refund_rate', 0.5);

        return $table
            ->query(
                User::query()
                    ->select('users.*')
                    ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.created_at >= ?) as recent_order_count', [now()->subHours($velocityWindowHours)])
                    ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) as total_order_count')
                    ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.refunded_amount_cents > 0) as refunded_order_count')
                    ->selectRaw('(SELECT COALESCE(SUM(total_cents), 0) FROM orders WHERE orders.user_id = users.id AND orders.created_at >= ?) as recent_order_value', [now()->subHours($velocityWindowHours)])
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
                    ->orderByRaw('recent_order_count DESC')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('User')
                    ->weight('bold')
                    ->url(fn (User $record): string => route('filament.control-plane.resources.users.view', ['record' => $record->id])),

                TextColumn::make('email')
                    ->label('Email')
                    ->copyable(),

                TextColumn::make('recent_order_count')
                    ->label("Orders (last {$velocityWindowHours}h)")
                    ->badge()
                    ->color(fn (int $state): string => $state >= $velocityOrders ? 'danger' : 'gray'),

                TextColumn::make('recent_order_value')
                    ->label('Value')
                    ->money('USD', divideBy: 100)
                    ->getStateUsing(fn (User $record): int => (int) $record->recent_order_value),

                TextColumn::make('refund_rate')
                    ->label('Refund Rate')
                    ->badge()
                    ->color(fn (string $state): string => (float) mb_rtrim($state, '%') / 100 >= $highRefundRate ? 'danger' : 'gray')
                    ->getStateUsing(function (User $record): string {
                        $total = (int) $record->total_order_count;
                        $refunded = (int) $record->refunded_order_count;

                        if ($total === 0) {
                            return '0%';
                        }

                        return round($refunded / $total * 100).'%';
                    }),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading('No suspicious users')
            ->emptyStateDescription('No users with unusual order velocity or refund patterns.')
            ->emptyStateIcon('heroicon-o-user-circle')
            ->paginated([10, 25]);
    }
}
