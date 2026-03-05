<?php

declare(strict_types=1);

namespace App\Filament\Resources\Chat\Schemas;

use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Enums\ConversationType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conversation Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Customer'),

                        TextEntry::make('tenant.name')
                            ->label('Tenant')
                            ->placeholder('—'),

                        TextEntry::make('type')
                            ->badge()
                            ->color(fn (ConversationType $state): string => match ($state) {
                                ConversationType::Store => 'info',
                                ConversationType::Support => 'warning',
                            }),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (ConversationStatus $state): string => match ($state) {
                                ConversationStatus::Open => 'success',
                                ConversationStatus::Closed => 'gray',
                            }),

                        TextEntry::make('subject')
                            ->placeholder('—'),

                        TextEntry::make('last_message_at')
                            ->label('Last Message')
                            ->dateTime(),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ]),
            ]);
    }
}
