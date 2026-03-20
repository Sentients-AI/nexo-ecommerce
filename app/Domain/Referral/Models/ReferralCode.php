<?php

declare(strict_types=1);

namespace App\Domain\Referral\Models;

use App\Domain\Referral\Enums\ReferralStatus;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ReferralCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReferralCode extends BaseModel
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
        'code',
        'referrer_reward_points',
        'referee_discount_percent',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
    ];

    /**
     * Get the referrer user.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the usages for this referral code.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(ReferralUsage::class);
    }

    /**
     * Determine if the referral code is currently valid.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Derive the current status of the referral code.
     */
    public function status(): ReferralStatus
    {
        if (! $this->is_active) {
            return ReferralStatus::Inactive;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return ReferralStatus::Expired;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return ReferralStatus::Exhausted;
        }

        return ReferralStatus::Active;
    }

    /**
     * Generate a shareable URL for this referral code.
     */
    public function generateShareableUrl(): string
    {
        return route('referral.use', ['code' => $this->code]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReferralCodeFactory
    {
        return ReferralCodeFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
