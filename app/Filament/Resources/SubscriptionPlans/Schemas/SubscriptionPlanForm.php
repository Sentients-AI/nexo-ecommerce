<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubscriptionPlans\Schemas;

use App\Domain\Subscription\Enums\BillingInterval;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

final class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),

                Select::make('billing_interval')
                    ->options(collect(BillingInterval::cases())->mapWithKeys(
                        fn (BillingInterval $i) => [$i->value => $i->label()]
                    ))
                    ->required()
                    ->columnSpan(1),
            ]),

            Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),

            Grid::make(2)->schema([
                TextInput::make('price_cents')
                    ->label('Price (cents)')
                    ->numeric()
                    ->required()
                    ->helperText('e.g. 999 = $9.99')
                    ->columnSpan(1),

                TextInput::make('stripe_price_id')
                    ->label('Stripe Price ID')
                    ->required()
                    ->placeholder('price_...')
                    ->columnSpan(1),
            ]),

            KeyValue::make('features')
                ->label('Features list')
                ->helperText('Add bullet-point features shown on the plan card.')
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}
