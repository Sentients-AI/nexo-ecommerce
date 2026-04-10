<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bundles\Pages;

use App\Filament\Resources\Bundles\BundleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateBundle extends CreateRecord
{
    protected static string $resource = BundleResource::class;
}
