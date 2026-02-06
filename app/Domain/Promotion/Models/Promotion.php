<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Models;

use App\Domain\Category\Models\Category;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Promotion\Enums\PromotionScope;
use App\Shared\Models\BaseModel;
use Carbon\Carbon;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Promotion extends BaseModel
{
    use HasFactory;

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
    ];

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
     * Calculate the discount for a given subtotal.
     */
    public function calculateDiscount(int $eligibleSubtotalCents): int
    {
        $discount = match ($this->discount_type) {
            DiscountType::Fixed => min($this->discount_value, $eligibleSubtotalCents),
            DiscountType::Percentage => (int) floor(($eligibleSubtotalCents * $this->discount_value) / 10000),
        };

        // Apply maximum discount cap if set
        if ($this->maximum_discount_cents !== null) {
            $discount = min($discount, $this->maximum_discount_cents);
        }

        return max(0, $discount);
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
