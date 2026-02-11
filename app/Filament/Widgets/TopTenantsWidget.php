<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Tenant\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class TopTenantsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top Tenants by Revenue';

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    public function table(Table $table): Table
    {
        $paidStatuses = [
            OrderStatus::Paid,
            OrderStatus::Packed,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Fulfilled,
            OrderStatus::PartiallyRefunded,
        ];

        return $table
            ->query(
                Tenant::query()
                    ->withCount([
                        'users' => fn (Builder $query) => $query->withoutGlobalScopes(),
                    ])
                    ->where('is_active', true)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('primary')
                    ->copyable(),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->state(fn (Tenant $record): int => Order::query()
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $record->id)
                        ->whereIn('status', $paidStatuses)
                        ->count())
                    ->alignCenter(),

                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->state(function (Tenant $record) use ($paidStatuses): string {
                        $revenue = Order::query()
                            ->withoutGlobalScopes()
                            ->where('tenant_id', $record->id)
                            ->whereIn('status', $paidStatuses)
                            ->sum('total_cents');

                        return '$'.number_format($revenue / 100, 2);
                    })
                    ->alignRight()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Order::query()
                            ->withoutGlobalScopes()
                            ->selectRaw('COALESCE(SUM(total_cents), 0)')
                            ->whereColumn('orders.tenant_id', 'tenants.id')
                            ->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, $paidStatuses)),
                        $direction
                    )),

                TextColumn::make('created_at')
                    ->label('Since')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25]);
    }
}
