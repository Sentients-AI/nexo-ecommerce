<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tax\Pages;

use App\Filament\Resources\Tax\TaxZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTaxZones extends ListRecords
{
    protected static string $resource = TaxZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
