<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\ContentPageResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateContentPage extends CreateRecord
{
    protected static string $resource = ContentPageResource::class;
}
