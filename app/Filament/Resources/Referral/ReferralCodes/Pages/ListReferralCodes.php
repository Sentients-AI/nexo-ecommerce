<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referral\ReferralCodes\Pages;

use App\Filament\Resources\Referral\ReferralCodes\ReferralCodeResource;
use Filament\Resources\Pages\ListRecords;

final class ListReferralCodes extends ListRecords
{
    protected static string $resource = ReferralCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
