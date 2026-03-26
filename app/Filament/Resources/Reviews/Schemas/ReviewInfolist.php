<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('product.name')->label('Product'),
                        TextEntry::make('user.name')->label('Customer'),
                        TextEntry::make('rating')
                            ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state)),
                        IconEntry::make('is_approved')->label('Approved')->boolean(),
                        TextEntry::make('title')->columnSpanFull(),
                        TextEntry::make('body')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Photos')
                    ->visible(fn ($record): bool => $record->photos->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('photos')
                            ->schema([
                                TextEntry::make('url')->label('Photo URL')->url(fn ($state) => $state),
                            ])
                            ->columns(1),
                    ]),

                Section::make('Replies')
                    ->visible(fn ($record): bool => $record->replies->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('replies')
                            ->schema([
                                TextEntry::make('user.name')->label('Author'),
                                IconEntry::make('is_merchant_reply')->label('Merchant Reply')->boolean(),
                                TextEntry::make('body')->columnSpanFull(),
                                TextEntry::make('created_at')->label('Posted')->dateTime(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
