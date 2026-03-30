<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shipping\Pages;

use App\Filament\Resources\Shipping\ShippingMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditShippingMethod extends EditRecord
{
    protected static string $resource = ShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
