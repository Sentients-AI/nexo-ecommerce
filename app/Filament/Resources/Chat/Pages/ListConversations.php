<?php

declare(strict_types=1);

namespace App\Filament\Resources\Chat\Pages;

use App\Filament\Resources\Chat\ConversationResource;
use Filament\Resources\Pages\ListRecords;

final class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
