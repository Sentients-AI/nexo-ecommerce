<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class TopProductsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top Products by Revenue (All-time)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->withoutTenancy()
                    ->whereHas('orderItems', fn (Builder $q) => $q->withoutGlobalScopes())
                    ->withCount(['orderItems as units_sold' => fn (Builder $q) => $q->withoutGlobalScopes()])
                    ->addSelect([
                        'revenue_cents' => OrderItem::query()
                            ->withoutGlobalScopes()
                            ->selectRaw('COALESCE(SUM(price_cents_snapshot * quantity), 0)')
                            ->whereColumn('order_items.product_id', 'products.id'),
                    ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->color('gray'),

                TextColumn::make('units_sold')
                    ->label('Units Sold')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('revenue_cents')
                    ->label('Revenue')
                    ->state(fn (Product $record): string => '$'.number_format(($record->revenue_cents ?? 0) / 100, 2))
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('price_cents')
                    ->label('Unit Price')
                    ->state(fn (Product $record): string => '$'.number_format($record->price_cents / 100, 2))
                    ->alignRight(),
            ])
            ->defaultSort('revenue_cents', 'desc')
            ->paginated([10, 25, 50]);
    }
}
