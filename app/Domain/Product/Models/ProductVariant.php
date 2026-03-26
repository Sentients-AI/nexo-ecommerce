<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Inventory\Models\Stock;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ProductVariant extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'sku',
        'price_cents',
        'sale_price',
        'is_active',
        'sort_order',
        'images',
    ];

    /**
     * Get the parent product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the stock record for this variant.
     */
    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class, 'variant_id');
    }

    /**
     * Get the attribute values assigned to this variant.
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(VariantAttributeValue::class, 'product_variant_attribute_values', 'product_variant_id', 'attribute_value_id')
            ->with('attributeType');
    }

    /**
     * Get the effective price (sale price if set, else variant price, else product price).
     */
    public function getEffectivePriceAttribute(): string
    {
        if ($this->sale_price !== null) {
            return $this->sale_price;
        }

        if ($this->price_cents !== null) {
            return $this->price_cents;
        }

        return $this->product->price_cents;
    }

    /**
     * Check if variant is on sale.
     */
    public function isOnSale(): bool
    {
        $price = $this->price_cents ?? $this->product->price_cents;

        return $this->sale_price !== null && $this->sale_price < $price;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
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
            'sort_order' => 'integer',
            'images' => 'array',
        ];
    }
}
