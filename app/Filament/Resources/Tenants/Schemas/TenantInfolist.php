<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Schemas;

use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TenantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Tenant Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID'),

                        TextEntry::make('name')
                            ->label('Name'),

                        TextEntry::make('slug')
                            ->label('Slug (subdomain)')
                            ->copyable(),

                        TextEntry::make('email')
                            ->label('Contact Email')
                            ->copyable()
                            ->placeholder('-'),

                        TextEntry::make('domain')
                            ->label('Custom Domain')
                            ->copyable()
                            ->placeholder('-'),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ]),

                Section::make('Settings')
                    ->schema([
                        TextEntry::make('settings')
                            ->label('Settings (JSON)')
                            ->formatStateUsing(fn (mixed $state): string => $state !== null
                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : 'No settings configured'
                            )
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),

                Section::make('Subscription Status')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('is_on_trial')
                            ->label('On Trial')
                            ->boolean()
                            ->state(fn ($record): bool => $record->isOnTrial()),

                        TextEntry::make('trial_ends_at')
                            ->label('Trial Ends')
                            ->dateTime()
                            ->placeholder('No trial'),

                        IconEntry::make('is_subscribed')
                            ->label('Subscribed')
                            ->boolean()
                            ->state(fn ($record): bool => $record->isSubscribed()),

                        TextEntry::make('subscribed_at')
                            ->label('Subscribed Since')
                            ->dateTime()
                            ->placeholder('Not subscribed'),
                    ]),

                Section::make('Statistics')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('users_count')
                            ->label('Users')
                            ->state(fn ($record): int => $record->users()->withoutGlobalScopes()->count()),

                        TextEntry::make('orders_count')
                            ->label('Orders')
                            ->state(fn ($record): int => Order::query()
                                ->withoutGlobalScopes()
                                ->where('tenant_id', $record->id)
                                ->count()),

                        TextEntry::make('products_count')
                            ->label('Products')
                            ->state(fn ($record): int => Product::query()
                                ->withoutGlobalScopes()
                                ->where('tenant_id', $record->id)
                                ->count()),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
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
