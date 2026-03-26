<?php

declare(strict_types=1);

namespace App\Filament\Resources\VariantAttributeTypes\Pages;

use App\Filament\Resources\VariantAttributeTypes\VariantAttributeTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditVariantAttributeType extends EditRecord
{
    protected static string $resource = VariantAttributeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
