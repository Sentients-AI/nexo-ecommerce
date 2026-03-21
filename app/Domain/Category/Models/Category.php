<?php

declare(strict_types=1);

namespace App\Domain\Category\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

final class Category extends BaseModel
{
    use BelongsToTenant, HasFactory, Searchable;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'is_active',
        'image',
        'sort_order',
    ];

    /**
     * Get the products for the category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Check if category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if category is a root category (no parent).
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
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
            'description' => $this->description ?? '',
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => now()->timestamp,
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
    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
