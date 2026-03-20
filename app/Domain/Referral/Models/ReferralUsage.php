<?php

declare(strict_types=1);

namespace App\Domain\Referral\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ReferralUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReferralUsage extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'referral_code_id',
        'referrer_user_id',
        'referee_user_id',
        'referrer_points_awarded',
        'referee_discount_percent',
        'referee_coupon_code',
    ];

    /**
     * Get the referral code.
     */
    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    /**
     * Get the referrer user.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    /**
     * Get the referee user.
     */
    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_user_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReferralUsageFactory
    {
        return ReferralUsageFactory::new();
    }
}
