<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tax\Pages;

use App\Filament\Resources\Tax\TaxZoneResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTaxZone extends CreateRecord
{
    protected static string $resource = TaxZoneResource::class;
}
