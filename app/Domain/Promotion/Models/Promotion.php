<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Models;

use App\Domain\Category\Models\Category;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\Carbon;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Promotion extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'scope',
        'auto_apply',
        'starts_at',
        'ends_at',
        'minimum_order_cents',
        'maximum_discount_cents',
        'usage_limit',
        'usage_count',
        'per_user_limit',
        'is_active',
        'buy_quantity',
        'get_quantity',
        'tiers',
        'is_flash_sale',
        'experiment_id',
        'variant',
    ];

    /**
     * Get the A/B experiment this promotion belongs to.
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(PromotionExperiment::class, 'experiment_id');
    }

    /**
     * Get the products this promotion applies to.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    /**
     * Get the categories this promotion applies to.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promotion_category');
    }

    /**
     * Get the usage records for this promotion.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    /**
     * Get orders that used this promotion.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if promotion is currently valid (active + within date range + under usage limit).
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($now->lt($this->starts_at) || $now->gt($this->ends_at)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if promotion applies to a specific product.
     */
    public function appliesTo(Product $product): bool
    {
        return match ($this->scope) {
            PromotionScope::All => true,
            PromotionScope::Product => $this->products->contains($product),
            PromotionScope::Category => $this->appliesToProductCategory($product),
        };
    }

    /**
     * Calculate the discount for a given subtotal (Fixed / Percentage).
     * BOGO and Tiered have dedicated methods called directly by CalculateDiscountAction.
     */
    public function calculateDiscount(int $eligibleSubtotalCents): int
    {
        $discount = match ($this->discount_type) {
            DiscountType::Fixed => min($this->discount_value, $eligibleSubtotalCents),
            DiscountType::Percentage => (int) floor(($eligibleSubtotalCents * $this->discount_value) / 10000),
            DiscountType::Bogo, DiscountType::Tiered => 0, // handled separately
        };

        if ($this->maximum_discount_cents !== null) {
            $discount = min($discount, $this->maximum_discount_cents);
        }

        return max(0, $discount);
    }

    /**
     * Calculate BOGO discount given a list of eligible item unit prices (cents, any order).
     * For every (buy_quantity + get_quantity) units, the get_quantity cheapest are free.
     *
     * @param  array<int>  $unitPricesCents  One entry per item quantity unit
     */
    public function calculateBogoDiscount(array $unitPricesCents): int
    {
        if (! $this->buy_quantity || ! $this->get_quantity) {
            return 0;
        }

        $groupSize = $this->buy_quantity + $this->get_quantity;
        $totalUnits = count($unitPricesCents);
        $freeUnitsCount = (int) floor($totalUnits / $groupSize) * $this->get_quantity;

        if ($freeUnitsCount === 0) {
            return 0;
        }

        // Free items are the cheapest ones
        sort($unitPricesCents);
        $discount = array_sum(array_slice($unitPricesCents, 0, $freeUnitsCount));

        if ($this->maximum_discount_cents !== null) {
            $discount = min($discount, $this->maximum_discount_cents);
        }

        return max(0, $discount);
    }

    /**
     * Calculate tiered discount for the given subtotal.
     * Picks the highest qualifying tier and applies its discount_bps.
     *
     * @param  array<array{min_cents: int, discount_bps: int}>  $tiers
     */
    public function calculateTieredDiscount(int $subtotalCents): int
    {
        $tiers = $this->tiers ?? [];
        if (empty($tiers)) {
            return 0;
        }

        // Sort tiers descending by min_cents to find the highest qualifying one first
        usort($tiers, fn (array $a, array $b) => $b['min_cents'] <=> $a['min_cents']);

        foreach ($tiers as $tier) {
            if ($subtotalCents >= $tier['min_cents']) {
                $discount = (int) floor(($subtotalCents * $tier['discount_bps']) / 10000);

                if ($this->maximum_discount_cents !== null) {
                    $discount = min($discount, $this->maximum_discount_cents);
                }

                return max(0, $discount);
            }
        }

        return 0;
    }

    /**
     * Whether this promotion is a flash sale (shows countdown on frontend).
     */
    public function isFlashSale(): bool
    {
        return (bool) $this->is_flash_sale;
    }

    /**
     * Seconds remaining until this promotion ends. Returns 0 if already expired.
     */
    public function timeRemainingSeconds(): int
    {
        if (! $this->ends_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->ends_at, false));
    }

    /**
     * Check if the minimum order requirement is met.
     */
    public function meetsMinimumOrder(int $subtotalCents): bool
    {
        if ($this->minimum_order_cents === null) {
            return true;
        }

        return $subtotalCents >= $this->minimum_order_cents;
    }

    /**
     * Increment the usage count.
     */
    public function incrementUsageCount(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Get formatted discount value for display.
     */
    public function getFormattedDiscountAttribute(): string
    {
        return match ($this->discount_type) {
            DiscountType::Fixed => '$'.number_format($this->discount_value / 100, 2),
            DiscountType::Percentage => number_format($this->discount_value / 100, 2).'%',
            DiscountType::Bogo => "Buy {$this->buy_quantity} Get {$this->get_quantity} Free",
            DiscountType::Tiered => 'Tiered Discount',
        };
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'scope' => PromotionScope::class,
            'auto_apply' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'minimum_order_cents' => 'integer',
            'maximum_discount_cents' => 'integer',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
            'buy_quantity' => 'integer',
            'get_quantity' => 'integer',
            'tiers' => 'array',
            'is_flash_sale' => 'boolean',
        ];
    }

    /**
     * Check if promotion applies to any of the product's categories.
     */
    private function appliesToProductCategory(Product $product): bool
    {
        if ($product->category_id === null) {
            return false;
        }

        return $this->categories->contains('id', $product->category_id);
    }
}
