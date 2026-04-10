<?php

declare(strict_types=1);

namespace App\Domain\Bundle\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\BundleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Bundle extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_cents',
        'compare_at_price_cents',
        'images',
        'is_active',
    ];

    /**
     * Get the items in this bundle.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    /**
     * Discount percentage vs individual prices.
     */
    public function getSavingsPercentAttribute(): ?int
    {
        if (! $this->compare_at_price_cents || $this->compare_at_price_cents <= 0) {
            return null;
        }

        return (int) round((1 - $this->price_cents / $this->compare_at_price_cents) * 100);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): BundleFactory
    {
        return BundleFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'images' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
