<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shipping\Pages;

use App\Filament\Resources\Shipping\ShippingMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListShippingMethods extends ListRecords
{
    protected static string $resource = ShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
