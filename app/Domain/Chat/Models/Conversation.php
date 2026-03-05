<?php

declare(strict_types=1);

namespace App\Domain\Chat\Models;

use App\Domain\Chat\Enums\ConversationStatus;
use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Conversation extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subject',
        'status',
        'type',
        'last_message_at',
        'tenant_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === ConversationStatus::Closed;
    }

    public function isStoreConversation(): bool
    {
        return $this->type === ConversationType::Store;
    }

    public function isSupportConversation(): bool
    {
        return $this->type === ConversationType::Support;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }
}
