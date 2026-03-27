<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Product Details')
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
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Product::class, 'slug', ignoreRecord: true),

                        TextInput::make('short_description')
                            ->label('Short Description')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => Category::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),
                    ]),

                Section::make('Pricing (Read-Only)')
                    ->description('Prices can only be changed via the dedicated Pricing page to ensure proper audit trails.')
                    ->columns(3)
                    ->schema([
                        Placeholder::make('sku_display')
                            ->label('SKU')
                            ->content(fn (?Product $record): string => $record?->sku ?? 'Will be set on create'),

                        Placeholder::make('price_display')
                            ->label('Price')
                            ->content(fn (?Product $record): string => $record instanceof Product
                                ? number_format($record->price_cents / 100, 2).' '.$record->currency
                                : 'Not set'
                            ),

                        Placeholder::make('sale_price_display')
                            ->label('Sale Price')
                            ->content(fn (?Product $record): string => $record?->sale_price
                                ? number_format($record->sale_price / 100, 2).' '.$record->currency
                                : 'No sale price'
                            ),

                        Placeholder::make('currency_display')
                            ->label('Currency')
                            ->content(fn (?Product $record): string => $record?->currency ?? 'USD'),
                    ])
                    ->visible(fn (?Product $record): bool => $record instanceof Product),

                Section::make('Initial Pricing')
                    ->description('Set the initial price for the new product.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(100)
                            ->unique(Product::class, 'sku', ignoreRecord: true),

                        TextInput::make('price_cents')
                            ->label('Price (in cents)')
                            ->numeric()
                            ->required()
                            ->minValue(0),

                        TextInput::make('sale_price')
                            ->label('Sale Price (in cents)')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                                'GBP' => 'GBP',
                                'MYR' => 'MYR',
                            ])
                            ->default('USD')
                            ->required(),
                    ])
                    ->visible(fn (?Product $record): bool => ! $record instanceof Product),

                Section::make('Digital Product')
                    ->description('Enable for downloadable products (e-books, software, assets). Files are stored privately and served via secure expiring links after payment.')
                    ->collapsed()
                    ->schema([
                        Toggle::make('is_downloadable')
                            ->label('Downloadable Product')
                            ->default(false)
                            ->live(),

                        FileUpload::make('download_file_path')
                            ->label('Downloadable File')
                            ->disk('private')
                            ->directory('downloads')
                            ->visibility('private')
                            ->preserveFilenames()
                            ->maxSize(102400) // 100 MB
                            ->visible(fn (Get $get): bool => (bool) $get('is_downloadable'))
                            ->required(fn (Get $get): bool => (bool) $get('is_downloadable'))
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(255),

                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
