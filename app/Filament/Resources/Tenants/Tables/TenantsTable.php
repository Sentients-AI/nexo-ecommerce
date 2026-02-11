<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Tables;

use App\Domain\Order\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable()
                    ->limit(30),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->state(fn ($record): int => Order::query()
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $record->id)
                        ->count())
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('viewAs')
                    ->label('View As')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Switch to Tenant View')
                    ->modalDescription(fn ($record): string => "You will view the admin panel as if you were a user of '$record->name'. All data will be filtered to this tenant.")
                    ->modalSubmitActionLabel('Switch to Tenant')
                    ->action(function ($record): void {
                        session(['filament_selected_tenant_id' => $record->id]);

                        Notification::make()
                            ->title("Switched to $record->name")
                            ->success()
                            ->send();

                        redirect(route('filament.control-plane.pages.operations-dashboard'));
                    }),
            ])
            ->bulkActions([]);
    }
}
