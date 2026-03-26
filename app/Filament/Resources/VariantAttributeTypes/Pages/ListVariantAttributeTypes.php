<?php

declare(strict_types=1);

namespace App\Filament\Resources\VariantAttributeTypes\Pages;

use App\Filament\Resources\VariantAttributeTypes\VariantAttributeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListVariantAttributeTypes extends ListRecords
{
    protected static string $resource = VariantAttributeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
