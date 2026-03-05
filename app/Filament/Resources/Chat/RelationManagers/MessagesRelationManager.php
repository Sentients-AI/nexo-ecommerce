<?php

declare(strict_types=1);

namespace App\Filament\Resources\Chat\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $recordTitleAttribute = 'body';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sender.name')
                    ->label('Sender')
                    ->sortable(),

                TextColumn::make('body')
                    ->label('Message')
                    ->limit(80),

                TextColumn::make('read_at')
                    ->label('Read')
                    ->dateTime()
                    ->placeholder('Unread')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->poll('10s')
            ->paginated(false);
    }
}
