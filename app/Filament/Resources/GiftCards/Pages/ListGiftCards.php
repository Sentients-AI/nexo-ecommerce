<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCards\Pages;

use App\Filament\Resources\GiftCards\GiftCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListGiftCards extends ListRecords
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
