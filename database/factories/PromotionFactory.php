<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Promotion>
 */
final class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Promotion',
            'code' => mb_strtoupper(fake()->unique()->lexify('????-????')),
            'description' => fake()->optional()->sentence(),
            'discount_type' => fake()->randomElement(DiscountType::cases()),
            'discount_value' => fake()->numberBetween(500, 2500), // 500 cents or 5%
            'scope' => PromotionScope::All,
            'auto_apply' => false,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'minimum_order_cents' => null,
            'maximum_discount_cents' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'per_user_limit' => null,
            'is_active' => true,
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
        ];
    }

    /**
     * Associate the promotion with a specific tenant.
     */
    public function forTenant(Tenant $tenant): self
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Set as a percentage discount.
     */
    public function percentage(int $basisPoints = 1000): self
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => DiscountType::Percentage,
            'discount_value' => $basisPoints, // 1000 = 10%
        ]);
    }

    /**
     * Set as a fixed discount.
     */
    public function fixed(int $cents = 1000): self
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => DiscountType::Fixed,
            'discount_value' => $cents,
        ]);
    }

    /**
     * Set as auto-apply promotion (no code required).
     */
    public function autoApply(): self
    {
        return $this->state(fn (array $attributes): array => [
            'code' => null,
            'auto_apply' => true,
        ]);
    }

    /**
     * Set as inactive.
     */
    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set as expired.
     */
    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Set as not yet started.
     */
    public function future(): self
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Set with usage limit.
     */
    public function withUsageLimit(int $limit = 100): self
    {
        return $this->state(fn (array $attributes): array => [
            'usage_limit' => $limit,
        ]);
    }

    /**
     * Set with per-user limit.
     */
    public function withPerUserLimit(int $limit = 1): self
    {
        return $this->state(fn (array $attributes): array => [
            'per_user_limit' => $limit,
        ]);
    }

    /**
     * Set with minimum order requirement.
     */
    public function withMinimumOrder(int $cents = 5000): self
    {
        return $this->state(fn (array $attributes): array => [
            'minimum_order_cents' => $cents,
        ]);
    }

    /**
     * Set with maximum discount cap.
     */
    public function withMaxDiscount(int $cents = 10000): self
    {
        return $this->state(fn (array $attributes): array => [
            'maximum_discount_cents' => $cents,
        ]);
    }

    /**
     * Set scope to specific products.
     */
    public function forProducts(): self
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => PromotionScope::Product,
        ]);
    }

    /**
     * Set scope to specific categories.
     */
    public function forCategories(): self
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => PromotionScope::Category,
        ]);
    }

    /**
     * Set usage count to near limit (for testing limit enforcement).
     */
    public function nearUsageLimit(): self
    {
        return $this->state(fn (array $attributes): array => [
            'usage_limit' => 10,
            'usage_count' => 9,
        ]);
    }

    /**
     * Set usage count to at limit.
     */
    public function atUsageLimit(): self
    {
        return $this->state(fn (array $attributes): array => [
            'usage_limit' => 10,
            'usage_count' => 10,
        ]);
    }

    /**
     * Set as a BOGO (Buy X Get Y) promotion.
     */
    public function bogo(int $buyQuantity = 2, int $getQuantity = 1): self
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => DiscountType::Bogo,
            'discount_value' => 0,
            'buy_quantity' => $buyQuantity,
            'get_quantity' => $getQuantity,
        ]);
    }

    /**
     * Set as a tiered discount promotion.
     *
     * @param  array<array{min_cents: int, discount_bps: int}>  $tiers
     */
    public function tiered(array $tiers = []): self
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => DiscountType::Tiered,
            'discount_value' => 0,
            'tiers' => $tiers ?: [
                ['min_cents' => 5000, 'discount_bps' => 500],
                ['min_cents' => 10000, 'discount_bps' => 1000],
            ],
        ]);
    }

    /**
     * Mark as a flash sale (shows countdown on storefront).
     */
    public function flashSale(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_flash_sale' => true,
        ]);
    }
}
