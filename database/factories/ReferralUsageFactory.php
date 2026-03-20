<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Referral\Models\ReferralUsage;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralUsage>
 */
final class ReferralUsageFactory extends Factory
{
    protected $model = ReferralUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $referralCode = ReferralCode::factory()->create();

        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'referral_code_id' => $referralCode->id,
            'referrer_user_id' => $referralCode->user_id,
            'referee_user_id' => User::factory(),
            'referrer_points_awarded' => 500,
            'referee_discount_percent' => 10,
            'referee_coupon_code' => 'REF-'.Str::upper(Str::random(8)),
        ];
    }

    /**
     * Associate the referral usage with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
