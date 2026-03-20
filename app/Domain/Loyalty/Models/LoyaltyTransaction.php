<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Loyalty\Enums\TransactionType;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\LoyaltyTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class LoyaltyTransaction extends BaseModel
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
        'loyalty_account_id',
        'type',
        'points',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
    ];

    /**
     * Get the loyalty account that owns the transaction.
     */
    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /**
     * Scope to earned transactions.
     */
    public function scopeEarned(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Earned->value);
    }

    /**
     * Scope to redeemed transactions.
     */
    public function scopeRedeemed(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Redeemed->value);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LoyaltyTransactionFactory
    {
        return LoyaltyTransactionFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'points' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
