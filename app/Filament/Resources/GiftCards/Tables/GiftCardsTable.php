<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCards\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class GiftCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('initial_balance_cents')
                    ->label('Original')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2))
                    ->sortable(),

                TextColumn::make('balance_cents')
                    ->label('Remaining')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2))
                    ->sortable()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : 'success'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->color(fn ($state): string => $state !== null && now()->gt($state) ? 'danger' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('redemptions_count')
                    ->label('Redemptions')
                    ->counts('redemptions')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}
