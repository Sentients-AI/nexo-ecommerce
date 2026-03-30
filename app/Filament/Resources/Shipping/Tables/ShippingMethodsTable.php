<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shipping\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),

                TextColumn::make('rate_cents')
                    ->label('Rate')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2))
                    ->sortable(),

                TextColumn::make('min_order_cents')
                    ->label('Free Over')
                    ->formatStateUsing(fn (?int $state): string => $state ? '$'.number_format($state / 100, 2) : '—'),

                TextColumn::make('estimated_days_min')
                    ->label('Est. Delivery')
                    ->formatStateUsing(fn ($record): string => $record->estimatedDeliveryLabel()),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
