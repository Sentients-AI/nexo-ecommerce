<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Shared\Domain\AuditLog;
use Filament\Resources\Pages\CreateRecord;

final class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function afterCreate(): void
    {
        AuditLog::log(
            action: 'promotion_created',
            targetType: 'promotion',
            targetId: $this->record->id,
            payload: [
                'name' => $this->record->name,
                'code' => $this->record->code,
                'discount_type' => $this->record->discount_type->value,
                'discount_value' => $this->record->discount_value,
                'scope' => $this->record->scope->value,
            ],
        );
    }
}
