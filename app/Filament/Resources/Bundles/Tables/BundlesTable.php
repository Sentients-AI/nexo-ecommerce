<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bundles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class BundlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price_cents')
                    ->label('Bundle Price')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2))
                    ->sortable(),

                TextColumn::make('compare_at_price_cents')
                    ->label('Compare-at')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? '$'.number_format($state / 100, 2)
                        : '—'
                    ),

                TextColumn::make('savings_percent')
                    ->label('Savings')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state}% off" : '—')
                    ->badge()
                    ->color('success'),

                TextColumn::make('items_count')
                    ->label('Products')
                    ->counts('items')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label('Active Status'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }
}
