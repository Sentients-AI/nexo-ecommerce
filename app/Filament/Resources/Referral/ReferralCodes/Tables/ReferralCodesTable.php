<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referral\ReferralCodes\Tables;

use App\Domain\Referral\Enums\ReferralStatus;
use App\Domain\Referral\Models\ReferralCode;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ReferralCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referrer.name')
                    ->label('Referrer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('referrer.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ReferralCode $record): string => $record->status()->label())
                    ->color(fn (ReferralCode $record): string => match ($record->status()) {
                        ReferralStatus::Active => 'success',
                        ReferralStatus::Expired => 'warning',
                        ReferralStatus::Exhausted => 'danger',
                        ReferralStatus::Inactive => 'gray',
                    }),

                TextColumn::make('used_count')
                    ->label('Uses')
                    ->state(fn (ReferralCode $record): string => $record->max_uses !== null
                        ? "{$record->used_count} / {$record->max_uses}"
                        : "{$record->used_count} / ∞"
                    )
                    ->sortable(),

                TextColumn::make('referrer_reward_points')
                    ->label('Referrer Points')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('referee_discount_percent')
                    ->label('Referee Discount')
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
