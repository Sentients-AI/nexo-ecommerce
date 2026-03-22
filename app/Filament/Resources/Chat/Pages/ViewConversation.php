<?php

declare(strict_types=1);

namespace App\Filament\Resources\Chat\Pages;

use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Models\Conversation;
use App\Filament\Resources\Chat\ConversationResource;
use App\Filament\Widgets\ConversationReplyWidget;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('close_conversation')
                ->label('Close Conversation')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Close Conversation')
                ->modalDescription('Are you sure you want to close this conversation? The customer will no longer be able to reply.')
                ->visible(fn (Conversation $record): bool => $record->isOpen())
                ->action(function (Conversation $record): void {
                    try {
                        $record->update(['status' => ConversationStatus::Closed]);

                        Notification::make()
                            ->title('Conversation Closed')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status']);
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Action Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ConversationReplyWidget::make(['conversationId' => $this->record->id]),
        ];
    }
}
