<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tax;

use App\Domain\Tax\Models\TaxZone;
use App\Filament\Concerns\HasTenantAwareness;
use App\Filament\Resources\Tax\Pages\CreateTaxZone;
use App\Filament\Resources\Tax\Pages\EditTaxZone;
use App\Filament\Resources\Tax\Pages\ListTaxZones;
use App\Filament\Resources\Tax\Schemas\TaxZoneForm;
use App\Filament\Resources\Tax\Tables\TaxZonesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class TaxZoneResource extends Resource
{
    use HasTenantAwareness;

    protected static ?string $model = TaxZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaxZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxZonesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxZones::route('/'),
            'create' => CreateTaxZone::route('/create'),
            'edit' => EditTaxZone::route('/{record}/edit'),
        ];
    }
}
