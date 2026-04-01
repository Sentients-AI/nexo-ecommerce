<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tax\Tables;

use App\Domain\Tax\Models\TaxZone;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TaxZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('country_code')
                    ->label('Country')
                    ->formatStateUsing(fn (?string $state): string => $state ?? '— (Global)')
                    ->sortable(),

                TextColumn::make('region_code')
                    ->label('Region')
                    ->formatStateUsing(fn (?string $state): string => $state ?? '—'),

                TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(fn ($record): string => number_format((float) $record->rate * 100, 2).'%')
                    ->sortable(),

                TextColumn::make('coverage')
                    ->label('Applies To')
                    ->state(fn (TaxZone $record): string => $record->coverageLabel()),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('country_code')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
