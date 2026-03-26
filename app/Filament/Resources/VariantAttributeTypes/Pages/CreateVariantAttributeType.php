<?php

declare(strict_types=1);

namespace App\Filament\Resources\VariantAttributeTypes\Pages;

use App\Filament\Resources\VariantAttributeTypes\VariantAttributeTypeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateVariantAttributeType extends CreateRecord
{
    protected static string $resource = VariantAttributeTypeResource::class;
}
