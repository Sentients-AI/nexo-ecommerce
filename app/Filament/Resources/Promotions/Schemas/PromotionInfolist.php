<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PromotionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Promotion Name'),

                        TextEntry::make('code')
                            ->label('Coupon Code')
                            ->placeholder('Auto-apply'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),

                        IconEntry::make('auto_apply')
                            ->label('Auto-Apply')
                            ->boolean(),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ]),

                Section::make('Discount Configuration')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('discount_type')
                            ->label('Discount Type')
                            ->formatStateUsing(fn ($state): string => $state->label()),

                        TextEntry::make('formatted_discount')
                            ->label('Discount Value'),

                        TextEntry::make('maximum_discount_cents')
                            ->label('Maximum Discount')
                            ->money('USD', divideBy: 100)
                            ->placeholder('No limit'),
                    ]),

                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('scope')
                            ->label('Applies To')
                            ->formatStateUsing(fn ($state): string => $state->label()),

                        TextEntry::make('products.name')
                            ->label('Products')
                            ->listWithLineBreaks()
                            ->placeholder('N/A'),

                        TextEntry::make('categories.name')
                            ->label('Categories')
                            ->listWithLineBreaks()
                            ->placeholder('N/A'),
                    ]),

                Section::make('Schedule')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('starts_at')
                            ->label('Start Date')
                            ->dateTime(),

                        TextEntry::make('ends_at')
                            ->label('End Date')
                            ->dateTime(),
                    ]),

                Section::make('Constraints & Usage')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('minimum_order_cents')
                            ->label('Minimum Order')
                            ->money('USD', divideBy: 100)
                            ->placeholder('No minimum'),

                        TextEntry::make('usage_limit')
                            ->label('Total Usage Limit')
                            ->placeholder('Unlimited'),

                        TextEntry::make('usage_count')
                            ->label('Times Used'),

                        TextEntry::make('per_user_limit')
                            ->label('Per User Limit')
                            ->placeholder('Unlimited'),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ]),
            ]);
    }
}
