<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referral\ReferralCodes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ReferralUsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('referee.name')
                    ->label('Referee')
                    ->searchable(),

                TextColumn::make('referee.email')
                    ->label('Referee Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('referrer_points_awarded')
                    ->label('Points Awarded')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('referee_discount_percent')
                    ->label('Discount Given')
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->sortable(),

                TextColumn::make('referee_coupon_code')
                    ->label('Coupon Code')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Used At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
