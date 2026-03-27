<?php

declare(strict_types=1);

namespace App\Filament\Resources\VariantAttributeTypes\RelationManagers;

use App\Domain\Product\Models\VariantAttributeValue;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $recordTitleAttribute = 'value';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('value')
                    ->label('Value')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug'),

                TextColumn::make('metadata')
                    ->label('Metadata')
                    ->state(fn (VariantAttributeValue $record): string => $record->metadata
                        ? collect($record->metadata)->map(fn ($v, $k) => "$k: $v")->join(', ')
                        : '—'
                    ),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    /**
     * @return array<Component>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->helperText('e.g. Red, Large, Cotton')
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (! $get('slug')) {
                                $set('slug', Str::slug($state ?? ''));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(100)
                        ->unique(VariantAttributeValue::class, 'slug', ignoreRecord: true),

                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0),

                    KeyValue::make('metadata')
                        ->label('Metadata')
                        ->helperText('Optional. For colors, add key "hex" with value "#FF0000".')
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ];
    }
}
