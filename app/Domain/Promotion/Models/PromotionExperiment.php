<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PromotionExperiment extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'hypothesis',
        'is_active',
        'started_at',
        'ended_at',
    ];

    /**
     * Get both variants (promotions) belonging to this experiment.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Promotion::class, 'experiment_id');
    }

    /**
     * Get the variant A promotion.
     */
    public function variantA(): HasMany
    {
        return $this->hasMany(Promotion::class, 'experiment_id')->where('variant', 'A');
    }

    /**
     * Get the variant B promotion.
     */
    public function variantB(): HasMany
    {
        return $this->hasMany(Promotion::class, 'experiment_id')->where('variant', 'B');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
