<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Tables;

use App\Domain\Review\Actions\ApproveReviewAction;
use App\Domain\Review\Actions\DeleteReviewAction;
use App\Domain\Review\Actions\RejectReviewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->sortable(),

                TextColumn::make('title')
                    ->limit(40)
                    ->searchable(),

                IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('is_approved')
                    ->label('Status')
                    ->options([
                        '1' => 'Approved',
                        '0' => 'Pending / Rejected',
                    ]),

                SelectFilter::make('rating')
                    ->options([
                        '5' => '5 Stars',
                        '4' => '4 Stars',
                        '3' => '3 Stars',
                        '2' => '2 Stars',
                        '1' => '1 Star',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => ! $record->is_approved)
                    ->action(fn ($record) => (new ApproveReviewAction)->execute($record))
                    ->requiresConfirmation(),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record->is_approved)
                    ->action(fn ($record) => (new RejectReviewAction)->execute($record))
                    ->requiresConfirmation(),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->action(fn ($record) => (new DeleteReviewAction)->execute($record))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkAction::make('approve_selected')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->action(fn (Collection $records) => $records->each(fn ($r) => (new ApproveReviewAction)->execute($r)))
                    ->requiresConfirmation(),

                BulkAction::make('reject_selected')
                    ->label('Reject Selected')
                    ->icon('heroicon-o-x-circle')
                    ->action(fn (Collection $records) => $records->each(fn ($r) => (new RejectReviewAction)->execute($r)))
                    ->requiresConfirmation(),

                DeleteBulkAction::make()
                    ->action(fn (Collection $records) => $records->each(fn ($r) => (new DeleteReviewAction)->execute($r))),
            ]);
    }
}
