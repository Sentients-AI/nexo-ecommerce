<?php

declare(strict_types=1);

namespace App\Filament\Resources\Loyalty\LoyaltyAccounts\RelationManagers;

use App\Domain\Loyalty\Enums\TransactionType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class LoyaltyTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (TransactionType $state): string => match ($state) {
                        TransactionType::Earned, TransactionType::Refunded => 'success',
                        TransactionType::Redeemed, TransactionType::Expired => 'danger',
                        TransactionType::Adjustment => 'info',
                    })
                    ->formatStateUsing(fn (TransactionType $state): string => $state->label()),

                TextColumn::make('points')
                    ->label('Points')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
