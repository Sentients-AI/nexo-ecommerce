<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Shared\Domain\AuditLog;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    /**
     * @var array<string, mixed>
     */
    private array $oldData = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldData = $this->record->only([
            'name', 'code', 'description', 'discount_type', 'discount_value',
            'scope', 'auto_apply', 'starts_at', 'ends_at', 'minimum_order_cents',
            'maximum_discount_cents', 'usage_limit', 'per_user_limit', 'is_active',
        ]);

        return $data;
    }

    protected function afterSave(): void
    {
        AuditLog::log(
            action: 'promotion_updated',
            targetType: 'promotion',
            targetId: $this->record->id,
            payload: [
                'old' => $this->oldData,
                'new' => $this->record->only([
                    'name', 'code', 'description', 'discount_type', 'discount_value',
                    'scope', 'auto_apply', 'starts_at', 'ends_at', 'minimum_order_cents',
                    'maximum_discount_cents', 'usage_limit', 'per_user_limit', 'is_active',
                ]),
            ],
        );
    }
}
