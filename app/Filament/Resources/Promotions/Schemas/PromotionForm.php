<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Schemas;

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionExperiment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Promotion Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label('Coupon Code')
                            ->maxLength(50)
                            ->unique(Promotion::class, 'code', ignoreRecord: true)
                            ->helperText('Leave empty for auto-apply promotions'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Toggle::make('auto_apply')
                            ->label('Auto-Apply')
                            ->helperText('Automatically apply this promotion when conditions are met')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('is_flash_sale')
                            ->label('Flash Sale')
                            ->helperText('Show a countdown timer on the storefront')
                            ->default(false),
                    ]),

                Section::make('Discount Configuration')
                    ->columns(2)
                    ->schema([
                        Select::make('discount_type')
                            ->label('Discount Type')
                            ->options(collect(DiscountType::cases())->mapWithKeys(
                                fn (DiscountType $type) => [$type->value => $type->label()]
                            ))
                            ->required()
                            ->live(),

                        TextInput::make('discount_value')
                            ->label(fn (Get $get): string => $get('discount_type') === DiscountType::Percentage->value
                                ? 'Discount Percentage (basis points, 1000 = 10%)'
                                : 'Discount Amount (cents)')
                            ->numeric()
                            ->required(fn (Get $get): bool => in_array($get('discount_type'), [
                                DiscountType::Fixed->value,
                                DiscountType::Percentage->value,
                            ], true))
                            ->minValue(1)
                            ->helperText(fn (Get $get): string => $get('discount_type') === DiscountType::Percentage->value
                                ? 'Enter 1000 for 10%, 500 for 5%, etc.'
                                : 'Enter amount in cents (1000 = $10.00)')
                            ->visible(fn (Get $get): bool => in_array($get('discount_type'), [
                                DiscountType::Fixed->value,
                                DiscountType::Percentage->value,
                            ], true)),

                        TextInput::make('maximum_discount_cents')
                            ->label('Maximum Discount (cents)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Cap the discount amount')
                            ->visible(fn (Get $get): bool => in_array($get('discount_type'), [
                                DiscountType::Percentage->value,
                                DiscountType::Bogo->value,
                                DiscountType::Tiered->value,
                            ], true)),

                        TextInput::make('buy_quantity')
                            ->label('Buy Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (Get $get): bool => $get('discount_type') === DiscountType::Bogo->value)
                            ->helperText('Number of items customer must buy')
                            ->visible(fn (Get $get): bool => $get('discount_type') === DiscountType::Bogo->value),

                        TextInput::make('get_quantity')
                            ->label('Get Quantity (Free)')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (Get $get): bool => $get('discount_type') === DiscountType::Bogo->value)
                            ->helperText('Number of items customer gets for free')
                            ->visible(fn (Get $get): bool => $get('discount_type') === DiscountType::Bogo->value),

                        Repeater::make('tiers')
                            ->label('Discount Tiers')
                            ->schema([
                                TextInput::make('min_cents')
                                    ->label('Minimum Subtotal (cents)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->helperText('e.g. 5000 = $50.00'),

                                TextInput::make('discount_bps')
                                    ->label('Discount (basis points)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->helperText('e.g. 1000 = 10%, 500 = 5%'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add Tier')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('discount_type') === DiscountType::Tiered->value),
                    ]),

                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        Select::make('scope')
                            ->label('Applies To')
                            ->options([
                                PromotionScope::All->value => PromotionScope::All->label(),
                                PromotionScope::Product->value => PromotionScope::Product->label(),
                                PromotionScope::Category->value => PromotionScope::Category->label(),
                            ])
                            ->default(PromotionScope::All->value)
                            ->required()
                            ->live(),

                        Select::make('products')
                            ->label('Products')
                            ->multiple()
                            ->relationship('products', 'name')
                            ->options(fn () => Product::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('scope') === PromotionScope::Product->value),

                        Select::make('categories')
                            ->label('Categories')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->options(fn () => Category::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('scope') === PromotionScope::Category->value),
                    ]),

                Section::make('Schedule')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Start Date')
                            ->required()
                            ->default(now()),

                        DateTimePicker::make('ends_at')
                            ->label('End Date')
                            ->required()
                            ->after('starts_at'),
                    ]),

                Section::make('A/B Experiment')
                    ->description('Assign this promotion to an A/B experiment to compare performance')
                    ->columns(2)
                    ->schema([
                        Select::make('experiment_id')
                            ->label('Experiment')
                            ->options(PromotionExperiment::query()->pluck('name', 'id'))
                            ->nullable()
                            ->searchable(),

                        Select::make('variant')
                            ->label('Variant')
                            ->options(['A' => 'Variant A', 'B' => 'Variant B'])
                            ->nullable(),
                    ]),

                Section::make('Constraints')
                    ->columns(2)
                    ->schema([
                        TextInput::make('minimum_order_cents')
                            ->label('Minimum Order Amount (cents)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Minimum subtotal required to use this promotion'),

                        TextInput::make('usage_limit')
                            ->label('Total Usage Limit')
                            ->numeric()
                            ->nullable()
                            ->helperText('Maximum number of times this promotion can be used'),

                        TextInput::make('per_user_limit')
                            ->label('Per User Limit')
                            ->numeric()
                            ->nullable()
                            ->helperText('Maximum number of times a single user can use this promotion'),
                    ]),
            ]);
    }
}
