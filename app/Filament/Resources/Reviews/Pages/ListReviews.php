<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\ListRecords;

final class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;
}
