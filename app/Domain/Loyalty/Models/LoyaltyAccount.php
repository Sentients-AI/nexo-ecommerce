<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\LoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LoyaltyAccount extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'points_balance',
        'total_points_earned',
        'total_points_redeemed',
    ];

    /**
     * Get the user that owns the loyalty account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions for this loyalty account.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * Check if the account has enough points to redeem.
     */
    public function canRedeem(int $points): bool
    {
        return $this->points_balance >= $points;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LoyaltyAccountFactory
    {
        return LoyaltyAccountFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_balance' => 'integer',
            'total_points_earned' => 'integer',
            'total_points_redeemed' => 'integer',
        ];
    }
}
