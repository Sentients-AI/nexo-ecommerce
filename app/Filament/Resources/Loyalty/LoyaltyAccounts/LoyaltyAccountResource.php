<?php

declare(strict_types=1);

namespace App\Filament\Resources\Loyalty\LoyaltyAccounts;

use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Filament\Concerns\HasTenantAwareness;
use App\Filament\Resources\Loyalty\LoyaltyAccounts\Pages\ListLoyaltyAccounts;
use App\Filament\Resources\Loyalty\LoyaltyAccounts\Pages\ViewLoyaltyAccount;
use App\Filament\Resources\Loyalty\LoyaltyAccounts\RelationManagers\LoyaltyTransactionsRelationManager;
use App\Filament\Resources\Loyalty\LoyaltyAccounts\Tables\LoyaltyAccountsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class LoyaltyAccountResource extends Resource
{
    use HasTenantAwareness;

    protected static ?string $model = LoyaltyAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return LoyaltyAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LoyaltyTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyAccounts::route('/'),
            'view' => ViewLoyaltyAccount::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
