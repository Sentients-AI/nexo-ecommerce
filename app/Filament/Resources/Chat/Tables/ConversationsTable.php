<?php

declare(strict_types=1);

namespace App\Filament\Resources\Chat\Tables;

use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Enums\ConversationType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

final class ConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => Auth::user()?->isSuperAdmin() ?? false),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (ConversationType $state): string => match ($state) {
                        ConversationType::Store => 'info',
                        ConversationType::Support => 'warning',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ConversationStatus $state): string => match ($state) {
                        ConversationStatus::Open => 'success',
                        ConversationStatus::Closed => 'gray',
                    }),

                TextColumn::make('subject')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('last_message_at')
                    ->label('Last Message')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ConversationStatus::class),

                SelectFilter::make('type')
                    ->options(ConversationType::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
