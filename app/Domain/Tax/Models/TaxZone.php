<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\TaxZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class TaxZone extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'country_code',
        'region_code',
        'rate',
        'is_active',
    ];

    /**
     * Human-readable label for this zone's coverage.
     */
    public function coverageLabel(): string
    {
        if ($this->country_code === null) {
            return 'Global (all countries)';
        }

        if ($this->region_code !== null) {
            return "{$this->country_code} — {$this->region_code}";
        }

        return $this->country_code;
    }

    protected static function newFactory(): TaxZoneFactory
    {
        return TaxZoneFactory::new();
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
