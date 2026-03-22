<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Chat\Actions\SendMessageAction;
use App\Domain\Chat\DTOs\SendMessageData;
use App\Domain\Chat\Models\Conversation;
use Exception;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

final class ConversationReplyWidget extends Widget
{
    public string $replyBody = '';

    public ?int $conversationId = null;

    protected string $view = 'filament.widgets.conversation-reply-widget';

    protected int|string|array $columnSpan = 'full';

    public function send(): void
    {
        $this->validate([
            'replyBody' => ['required', 'string', 'max:5000'],
        ]);

        if ($this->conversationId === null) {
            return;
        }

        $conversation = Conversation::query()->withoutTenancy()->find($this->conversationId);

        if ($conversation === null || $conversation->isClosed()) {
            Notification::make()
                ->title('Cannot Reply')
                ->body('This conversation is closed.')
                ->warning()
                ->send();

            return;
        }

        try {
            app(SendMessageAction::class)->execute(new SendMessageData(
                conversationId: $conversation->id,
                senderId: auth()->id(),
                body: $this->replyBody,
            ));

            $this->replyBody = '';

            Notification::make()
                ->title('Reply Sent')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Failed to Send')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function isConversationOpen(): bool
    {
        if ($this->conversationId === null) {
            return false;
        }

        $conversation = Conversation::query()->withoutTenancy()->find($this->conversationId);

        return $conversation?->isOpen() ?? false;
    }
}
