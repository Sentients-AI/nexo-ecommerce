<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Tables;

use App\Domain\Promotion\Actions\GenerateBulkCodesAction;
use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Domain\Promotion\Models\Promotion;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable()
                    ->limit(30),

                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->placeholder('Auto-apply'),

                TextColumn::make('formatted_discount')
                    ->label('Discount')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('discount_value', $direction)),

                TextColumn::make('scope')
                    ->label('Scope')
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        PromotionScope::All => 'success',
                        PromotionScope::Product => 'info',
                        PromotionScope::Category => 'warning',
                    }),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->date()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->date()
                    ->sortable()
                    ->color(fn ($state): string => Carbon::parse($state)->isPast() ? 'danger' : 'success'),

                TextColumn::make('usage_count')
                    ->label('Usage')
                    ->formatStateUsing(fn ($record): string => $record->usage_limit
                        ? "{$record->usage_count}/{$record->usage_limit}"
                        : (string) $record->usage_count
                    )
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('auto_apply')
                    ->label('Auto')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state ? mb_substr((string) $state, 0, 8).'…' : null)
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status'),

                TernaryFilter::make('auto_apply')
                    ->label('Auto-Apply'),

                SelectFilter::make('discount_type')
                    ->label('Discount Type')
                    ->options([
                        DiscountType::Fixed->value => DiscountType::Fixed->label(),
                        DiscountType::Percentage->value => DiscountType::Percentage->label(),
                    ]),

                SelectFilter::make('scope')
                    ->label('Scope')
                    ->options([
                        PromotionScope::All->value => PromotionScope::All->label(),
                        PromotionScope::Product->value => PromotionScope::Product->label(),
                        PromotionScope::Category->value => PromotionScope::Category->label(),
                    ]),
            ])
            ->recordActions([
                Action::make('generateBulkCodes')
                    ->label('Generate Bulk Codes')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->form([
                        TextInput::make('count')
                            ->label('Number of codes')
                            ->numeric()
                            ->required()
                            ->default(10)
                            ->minValue(1)
                            ->maxValue(500),
                        TextInput::make('prefix')
                            ->label('Code prefix (optional)')
                            ->placeholder('e.g. SUMMER')
                            ->maxLength(20),
                    ])
                    ->action(function (Promotion $record, array $data, GenerateBulkCodesAction $generator): void {
                        $generated = $generator->execute(
                            template: $record,
                            count: (int) $data['count'],
                            prefix: $data['prefix'] ?? '',
                        );

                        Notification::make()
                            ->title("Generated {$generated->count()} codes")
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }
}
