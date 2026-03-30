<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shipping\Schemas;

use App\Domain\Shipping\Enums\ShippingType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Shipping Method')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),

                    TextInput::make('description')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('type')
                        ->options(collect(ShippingType::cases())->mapWithKeys(
                            fn (ShippingType $t) => [$t->value => $t->label()]
                        ))
                        ->required()
                        ->live()
                        ->default(ShippingType::FlatRate->value),

                    TextInput::make('rate_cents')
                        ->label('Rate (cents)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('e.g. 1000 = $10.00')
                        ->visible(fn (Get $get): bool => in_array(
                            $get('type'),
                            [ShippingType::FlatRate->value, ShippingType::FreeOverAmount->value],
                            true
                        )),

                    TextInput::make('min_order_cents')
                        ->label('Free over amount (cents)')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('e.g. 10000 = free shipping over $100.00')
                        ->visible(fn (Get $get): bool => $get('type') === ShippingType::FreeOverAmount->value),
                ]),

            Section::make('Delivery Estimate')
                ->columns(2)
                ->schema([
                    TextInput::make('estimated_days_min')
                        ->label('Min days')
                        ->numeric()
                        ->minValue(0),

                    TextInput::make('estimated_days_max')
                        ->label('Max days')
                        ->numeric()
                        ->minValue(0),
                ]),

            Section::make('Settings')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first'),
                ]),
        ]);
    }
}
