<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Order\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

final class FlaggedOrdersWidget extends TableWidget
{
    protected static ?string $heading = 'Flagged Orders';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $lookback = now()->subDays(config('fraud.lookback_days', 7));
        $attemptsThreshold = (int) config('fraud.payment_attempts_threshold', 3);
        $highValueThreshold = (int) config('fraud.high_value_threshold_cents', 50000);
        $newUserDays = (int) config('fraud.new_user_days', 7);

        return $table
            ->query(
                Order::query()
                    ->with(['user', 'paymentIntent'])
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
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->weight('bold')
                    ->url(fn (Order $record): string => route('filament.control-plane.resources.orders.view', ['record' => $record->id])),

                TextColumn::make('customer')
                    ->label('Customer')
                    ->getStateUsing(fn (Order $record): string => $record->user?->name ?? $record->guest_name ?? 'Guest'),

                TextColumn::make('customer_email')
                    ->label('Email')
                    ->getStateUsing(fn (Order $record): string => $record->user?->email ?? $record->guest_email ?? '—')
                    ->copyable(),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->money(fn (Order $record): string => $record->currency ?? 'USD', divideBy: 100)
                    ->weight('bold'),

                TextColumn::make('risk_signals')
                    ->label('Risk Signals')
                    ->badge()
                    ->color('danger')
                    ->separator(',')
                    ->getStateUsing(function (Order $record) use ($attemptsThreshold, $highValueThreshold, $newUserDays): string {
                        $signals = [];

                        if ($record->paymentIntent && $record->paymentIntent->attempts >= $attemptsThreshold) {
                            $signals[] = 'Multi-attempt ('.$record->paymentIntent->attempts.'x)';
                        }

                        if ($record->total_cents >= $highValueThreshold
                            && $record->user
                            && $record->user->created_at >= now()->subDays($newUserDays)) {
                            $signals[] = 'New-user high-value';
                        }

                        if ($record->guest_email && $record->total_cents >= $highValueThreshold) {
                            $signals[] = 'Guest high-value';
                        }

                        return implode(',', $signals);
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid', 'fulfilled', 'delivered', 'shipped', 'packed' => 'success',
                        'pending', 'awaiting_payment' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => str($state)->replace('_', ' ')->title()),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No flagged orders')
            ->emptyStateDescription('No orders matching fraud signals in the lookback window.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->paginated([10, 25, 50]);
    }
}
