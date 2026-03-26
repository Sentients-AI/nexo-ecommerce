<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\VariantAttributeTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VariantAttributeType extends BaseModel
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
        'sort_order',
    ];

    /**
     * Get the attribute values for this type.
     */
    public function values(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class, 'attribute_type_id')->orderBy('sort_order');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): VariantAttributeTypeFactory
    {
        return VariantAttributeTypeFactory::new();
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
        ];
    }
}
