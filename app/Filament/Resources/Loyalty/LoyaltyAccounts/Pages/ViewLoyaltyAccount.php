<?php

declare(strict_types=1);

namespace App\Filament\Resources\Loyalty\LoyaltyAccounts\Pages;

use App\Filament\Resources\Loyalty\LoyaltyAccounts\LoyaltyAccountResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewLoyaltyAccount extends ViewRecord
{
    protected static string $resource = LoyaltyAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
