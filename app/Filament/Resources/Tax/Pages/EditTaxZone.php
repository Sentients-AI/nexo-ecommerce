<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tax\Pages;

use App\Filament\Resources\Tax\TaxZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditTaxZone extends EditRecord
{
    protected static string $resource = TaxZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
