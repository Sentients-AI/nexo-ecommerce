<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referral\ReferralCodes;

use App\Domain\Referral\Models\ReferralCode;
use App\Filament\Concerns\HasTenantAwareness;
use App\Filament\Resources\Referral\ReferralCodes\Pages\ListReferralCodes;
use App\Filament\Resources\Referral\ReferralCodes\Pages\ViewReferralCode;
use App\Filament\Resources\Referral\ReferralCodes\RelationManagers\ReferralUsagesRelationManager;
use App\Filament\Resources\Referral\ReferralCodes\Tables\ReferralCodesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class ReferralCodeResource extends Resource
{
    use HasTenantAwareness;

    protected static ?string $model = ReferralCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'code';

    public static function table(Table $table): Table
    {
        return ReferralCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ReferralUsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralCodes::route('/'),
            'view' => ViewReferralCode::route('/{record}'),
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
