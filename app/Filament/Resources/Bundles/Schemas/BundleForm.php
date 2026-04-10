<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bundles\Schemas;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class BundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Bundle Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set(
                                'slug',
                                str($state)->slug()->value()
                            )),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Section::make('Pricing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price_cents')
                            ->label('Bundle Price (cents)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->helperText('e.g. 1999 = $19.99'),

                        TextInput::make('compare_at_price_cents')
                            ->label('Compare-at Price (cents)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Sum of individual prices — used to show savings'),
                    ]),

                Section::make('Products in Bundle')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => Product::query()
                                        ->where('is_active', true)
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Select::make('variant_id')
                                    ->label('Variant (optional)')
                                    ->options(fn (Get $get) => $get('product_id')
                                        ? ProductVariant::query()
                                            ->where('product_id', $get('product_id'))
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn ($v) => [$v->id => $v->sku])
                                        : []
                                    )
                                    ->nullable()
                                    ->placeholder('Any variant'),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add Product')
                            ->minItems(2)
                            ->defaultItems(2),
                    ]),
            ]);
    }
}
