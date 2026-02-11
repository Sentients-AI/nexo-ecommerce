<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Tenant\Models\Tenant;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class RecentTenantActivityWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Tenant Signups';

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Tenant')
                    ->searchable(),

                TextColumn::make('slug')
                    ->label('Subdomain')
                    ->color('primary'),

                TextColumn::make('email')
                    ->label('Contact')
                    ->copyable()
                    ->placeholder('-'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('is_on_trial')
                    ->label('On Trial')
                    ->boolean()
                    ->state(fn (Tenant $record): bool => $record->isOnTrial()),

                IconColumn::make('is_subscribed')
                    ->label('Subscribed')
                    ->boolean()
                    ->state(fn (Tenant $record): bool => $record->isSubscribed()),

                TextColumn::make('created_at')
                    ->label('Signed Up')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
