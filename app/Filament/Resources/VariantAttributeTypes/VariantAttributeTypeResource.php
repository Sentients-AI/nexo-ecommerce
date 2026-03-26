<?php

declare(strict_types=1);

namespace App\Filament\Resources\VariantAttributeTypes;

use App\Domain\Product\Models\VariantAttributeType;
use App\Filament\Concerns\HasTenantAwareness;
use App\Filament\Resources\VariantAttributeTypes\Pages\CreateVariantAttributeType;
use App\Filament\Resources\VariantAttributeTypes\Pages\EditVariantAttributeType;
use App\Filament\Resources\VariantAttributeTypes\Pages\ListVariantAttributeTypes;
use App\Filament\Resources\VariantAttributeTypes\RelationManagers\AttributeValuesRelationManager;
use App\Filament\Resources\VariantAttributeTypes\Schemas\VariantAttributeTypeForm;
use App\Filament\Resources\VariantAttributeTypes\Tables\VariantAttributeTypesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class VariantAttributeTypeResource extends Resource
{
    use HasTenantAwareness;

    protected static ?string $model = VariantAttributeType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return VariantAttributeTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VariantAttributeTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AttributeValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVariantAttributeTypes::route('/'),
            'create' => CreateVariantAttributeType::route('/create'),
            'edit' => EditVariantAttributeType::route('/{record}/edit'),
        ];
    }
}
