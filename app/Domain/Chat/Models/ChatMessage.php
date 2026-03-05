<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChatMessage extends BaseModel
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'read_at',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Use withoutGlobalScopes to ensure sender is always loadable regardless of tenant scope.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withoutGlobalScopes();
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ChatMessageFactory
    {
        return ChatMessageFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
