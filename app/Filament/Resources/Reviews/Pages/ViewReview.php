<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;
}
