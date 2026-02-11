<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Schemas;

use App\Domain\Tenant\Models\Tenant;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Tenant Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (! $get('slug') || $get('slug') === Str::slug($get('name_original') ?? '')) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug (subdomain)')
                            ->required()
                            ->maxLength(63)
                            ->unique(Tenant::class, 'slug', ignoreRecord: true)
                            ->helperText('Used as subdomain: {slug}.yourdomain.com'),

                        TextInput::make('email')
                            ->label('Contact Email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('domain')
                            ->label('Custom Domain')
                            ->maxLength(255)
                            ->placeholder('e.g., store.customdomain.com')
                            ->helperText('Optional custom domain for this tenant'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive tenants cannot access the platform'),
                    ]),

                Section::make('Settings')
                    ->description('JSON configuration for this tenant. You can store any custom settings here.')
                    ->schema([
                        Textarea::make('settings')
                            ->label('Settings (JSON)')
                            ->rows(10)
                            ->json()
                            ->placeholder('{"theme": "dark", "currency": "USD"}')
                            ->helperText('Enter valid JSON or leave empty for defaults'),
                    ]),

                Section::make('Subscription')
                    ->columns(2)
                    ->schema([
                        TextInput::make('trial_ends_at')
                            ->label('Trial Ends At')
                            ->type('datetime-local')
                            ->helperText('Leave empty if not on trial'),

                        TextInput::make('subscribed_at')
                            ->label('Subscribed At')
                            ->type('datetime-local')
                            ->helperText('Leave empty if not yet subscribed'),
                    ]),
            ]);
    }
}
