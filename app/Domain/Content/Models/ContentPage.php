<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

final class ContentPage extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'meta_description',
        'is_published',
        'sort_order',
    ];

    /**
     * Scope to only published pages.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
