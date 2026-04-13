<?php

declare(strict_types=1);

namespace App\Filament\Resources\PromotionExperiments\Pages;

use App\Filament\Resources\PromotionExperiments\PromotionExperimentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPromotionExperiments extends ListRecords
{
    protected static string $resource = PromotionExperimentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
