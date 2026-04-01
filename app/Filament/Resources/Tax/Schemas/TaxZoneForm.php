<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tax\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TaxZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Tax Zone')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull()
                        ->placeholder('e.g. Malaysia SST, UK VAT Standard'),

                    TextInput::make('country_code')
                        ->label('Country Code')
                        ->maxLength(2)
                        ->nullable()
                        ->placeholder('MY, US, GB — leave blank for global fallback')
                        ->helperText('ISO 3166-1 alpha-2 (2 letters). Leave blank to apply as global default.'),

                    TextInput::make('region_code')
                        ->label('Region / State Code')
                        ->maxLength(10)
                        ->nullable()
                        ->placeholder('e.g. KL, CA, NSW')
                        ->helperText('Optional. Overrides country-level zone for this region.'),

                    TextInput::make('rate')
                        ->label('Tax Rate')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.0001)
                        ->placeholder('0.1000')
                        ->helperText('Fractional rate: 0.1000 = 10%, 0.06 = 6%')
                        ->columnSpanFull(),
                ]),

            Section::make('Status')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
        ]);
    }
}
