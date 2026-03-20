<?php

declare(strict_types=1);

namespace App\Filament\Resources\Loyalty\LoyaltyAccounts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class LoyaltyAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('points_balance')
                    ->label('Balance')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_points_earned')
                    ->label('Total Earned')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_points_redeemed')
                    ->label('Total Redeemed')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('points_balance', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
