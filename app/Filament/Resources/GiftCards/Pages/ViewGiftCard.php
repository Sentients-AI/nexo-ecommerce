<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCards\Pages;

use App\Filament\Resources\GiftCards\GiftCardResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewGiftCard extends ViewRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleActive')
                ->label(fn () => $this->record->is_active ? 'Deactivate' : 'Activate')
                ->color(fn () => $this->record->is_active ? 'danger' : 'success')
                ->action(function (): void {
                    $this->record->update(['is_active' => ! $this->record->is_active]);
                    $this->refreshFormData(['is_active']);
                }),
        ];
    }
}
