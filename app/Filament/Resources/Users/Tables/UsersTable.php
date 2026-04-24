<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Role\Models\Role;
use App\Domain\User\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

final class UsersTable
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
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'danger',
                        'finance' => 'warning',
                        'support' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role_id')
                    ->label('Role')
                    ->options(fn () => Role::query()->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Impersonate User')
                    ->modalDescription(fn (User $record): string => "You will be logged in as {$record->name}. A banner will appear allowing you to stop impersonating.")
                    ->visible(fn (): bool => Auth::user()?->isSuperAdmin() ?? false)
                    ->action(function (User $record): void {
                        /** @var User $admin */
                        $admin = Auth::user();

                        if ($record->isSuperAdmin()) {
                            Notification::make()
                                ->title('Cannot impersonate another super admin.')
                                ->danger()
                                ->send();

                            return;
                        }

                        session([
                            'impersonating_as' => $record->id,
                            'original_admin_id' => $admin->id,
                        ]);

                        Auth::loginUsingId($record->id);
                    })
                    ->successRedirectUrl('/en'),
                ViewAction::make(),
            ]);
    }
}
