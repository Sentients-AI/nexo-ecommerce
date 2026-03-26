<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ReviewReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReviewReply extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['tenant_id', 'review_id', 'user_id', 'body', 'is_merchant_reply'];

    /**
     * Get the review that this reply belongs to.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * Get the user who wrote this reply.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReviewReplyFactory
    {
        return ReviewReplyFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_merchant_reply' => 'boolean'];
    }
}
