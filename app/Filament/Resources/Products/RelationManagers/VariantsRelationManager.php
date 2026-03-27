<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Product\Actions\CreateProductVariant;
use App\Domain\Product\Actions\DeleteProductVariant;
use App\Domain\Product\Actions\UpdateProductVariant;
use App\Domain\Product\DTOs\ProductVariantData;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Product\Models\VariantAttributeType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('attributeValues')
                    ->label('Attributes')
                    ->state(fn (ProductVariant $record): string => $record->attributeValues
                        ->map(fn ($v) => $v->attributeType->name.': '.$v->value)
                        ->join(', ')
                    ),

                TextColumn::make('price_cents')
                    ->label('Price Override')
                    ->state(fn (ProductVariant $record): string => $record->price_cents !== null
                        ? number_format((float) $record->price_cents / 100, 2)
                        : '— (uses product price)'
                    ),

                TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->state(fn (ProductVariant $record): string => $record->sale_price !== null
                        ? number_format((float) $record->sale_price / 100, 2)
                        : '—'
                    ),

                TextColumn::make('stock.quantity_available')
                    ->label('Stock')
                    ->default(0)
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'danger'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, string $model): ProductVariant {
                        $data['product_id'] = $this->getOwnerRecord()->id;

                        return app(CreateProductVariant::class)->execute(
                            ProductVariantData::fromRequest($data)
                        );
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->using(function (ProductVariant $record, array $data): ProductVariant {
                        $data['product_id'] = $record->product_id;

                        return app(UpdateProductVariant::class)->execute(
                            $record,
                            ProductVariantData::fromRequest($data)
                        );
                    }),

                DeleteAction::make()
                    ->using(function (ProductVariant $record): void {
                        app(DeleteProductVariant::class)->execute($record);
                    }),
            ])
            ->defaultSort('sort_order');
    }

    /**
     * @return array<Component>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make('Variant Details')
                ->columns(2)
                ->schema([
                    TextInput::make('sku')
                        ->label('SKU')
                        ->required()
                        ->maxLength(100)
                        ->unique(ProductVariant::class, 'sku', ignoreRecord: true),

                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0),

                    TextInput::make('price_cents')
                        ->label('Price Override (cents)')
                        ->helperText('Leave empty to inherit from parent product.')
                        ->numeric()
                        ->minValue(0)
                        ->nullable(),

                    TextInput::make('sale_price')
                        ->label('Sale Price (cents)')
                        ->numeric()
                        ->minValue(0)
                        ->nullable(),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->columnSpanFull(),
                ]),

            Section::make('Attributes')
                ->schema([
                    Select::make('attribute_value_ids')
                        ->label('Attribute Values')
                        ->multiple()
                        ->options(function (): array {
                            $ownerRecord = $this->getOwnerRecord();

                            return VariantAttributeType::query()
                                ->where('tenant_id', $ownerRecord->tenant_id)
                                ->with('values')
                                ->get()
                                ->flatMap(fn (VariantAttributeType $type) => $type->values->mapWithKeys(
                                    fn ($v) => [$v->id => $type->name.': '.$v->value]
                                ))
                                ->toArray();
                        })
                        ->searchable()
                        ->required(),
                ]),
        ];
    }
}
