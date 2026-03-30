<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shipping;

use App\Domain\Shipping\Models\ShippingMethod;
use App\Filament\Concerns\HasTenantAwareness;
use App\Filament\Resources\Shipping\Pages\CreateShippingMethod;
use App\Filament\Resources\Shipping\Pages\EditShippingMethod;
use App\Filament\Resources\Shipping\Pages\ListShippingMethods;
use App\Filament\Resources\Shipping\Schemas\ShippingMethodForm;
use App\Filament\Resources\Shipping\Tables\ShippingMethodsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class ShippingMethodResource extends Resource
{
    use HasTenantAwareness;

    protected static ?string $model = ShippingMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ShippingMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingMethodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingMethods::route('/'),
            'create' => CreateShippingMethod::route('/create'),
            'edit' => EditShippingMethod::route('/{record}/edit'),
        ];
    }
}
