<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\VariantAttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class VariantAttributeValue extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attribute_type_id',
        'value',
        'slug',
        'sort_order',
        'metadata',
    ];

    /**
     * Get the attribute type that owns this value.
     */
    public function attributeType(): BelongsTo
    {
        return $this->belongsTo(VariantAttributeType::class, 'attribute_type_id');
    }

    /**
     * Get the product variants that use this attribute value.
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_attribute_values', 'attribute_value_id', 'product_variant_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): VariantAttributeValueFactory
    {
        return VariantAttributeValueFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
