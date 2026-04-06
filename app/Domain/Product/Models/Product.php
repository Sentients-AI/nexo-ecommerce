<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Category\Models\Category;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Question\Models\Question;
use App\Domain\Review\Models\Review;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

final class Product extends BaseModel
{
    use BelongsToTenant, HasFactory, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sku',
        'name',
        'slug',
        'description',
        'short_description',
        'price_cents',
        'sale_price',
        'category_id',
        'is_active',
        'is_featured',
        'is_downloadable',
        'download_file_path',
        'view_count',
        'images',
        'meta_title',
        'meta_description',
        'currency',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the stock record for the product.
     */
    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    /**
     * Get the price history records for the product.
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderByDesc('created_at');
    }

    /**
     * Get the variants for this product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /**
     * Get the active variants for this product.
     */
    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Determine if this product has variants.
     */
    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    /**
     * Get the download tokens for this product.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(ProductDownload::class);
    }

    /**
     * Get the reviews for the product.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the average rating from approved reviews.
     */
    public function getAverageRatingAttribute(): ?float
    {
        return $this->reviews()->approved()->avg('rating');
    }

    /**
     * Get the count of approved reviews.
     */
    public function getReviewCountAttribute(): int
    {
        return $this->reviews()->approved()->count();
    }

    /**
     * Get the effective price (sale price if available, otherwise regular price).
     */
    public function getEffectivePriceAttribute(): string
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Check if product is on sale.
     */
    public function isOnSale(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }

    /**
     * Get the discount percentage.
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (! $this->isOnSale()) {
            return null;
        }

        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (int) $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description ?? '',
            'short_description' => $this->short_description ?? '',
            'price_cents' => (int) $this->price_cents,
            'sale_price' => (int) ($this->sale_price ?? 0),
            'currency' => $this->currency,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'category_id' => (int) ($this->category_id ?? 0),
            'category_name' => $this->category?->name ?? '',
            'view_count' => (int) $this->view_count,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_downloadable' => 'boolean',
            'view_count' => 'integer',
            'images' => 'array',
            'meta_description' => 'json',
        ];
    }
}
