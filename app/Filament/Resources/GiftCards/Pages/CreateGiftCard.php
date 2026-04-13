<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCards\Pages;

use App\Filament\Resources\GiftCards\GiftCardResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateGiftCard extends CreateRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return GiftCardResource::handleRecordCreation($data);
    }
}
