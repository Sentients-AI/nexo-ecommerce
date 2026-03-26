<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Review extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'title',
        'body',
        'is_approved',
    ];

    /**
     * Get the product that this review belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who wrote this review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * Get the photos attached to this review.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ReviewPhoto::class)->orderBy('order');
    }

    /**
     * Get the replies to this review.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ReviewReply::class)->orderBy('created_at');
    }

    /**
     * Get the votes on this review.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    /**
     * Scope to only approved reviews.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
        ];
    }
}
