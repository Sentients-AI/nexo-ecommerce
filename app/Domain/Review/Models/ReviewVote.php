<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ReviewVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReviewVote extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['tenant_id', 'review_id', 'user_id', 'is_helpful'];

    /**
     * Get the review that this vote belongs to.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * Get the user who cast this vote.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReviewVoteFactory
    {
        return ReviewVoteFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_helpful' => 'boolean'];
    }
}
