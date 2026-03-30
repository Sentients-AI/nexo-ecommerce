<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Models;

use App\Domain\Shipping\Enums\ShippingType;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class ShippingMethod extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'rate_cents',
        'min_order_cents',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
        'sort_order',
    ];

    /**
     * Determine the effective shipping cost for a given subtotal.
     */
    public function calculateCost(int $subtotalCents): int
    {
        return match ($this->type) {
            ShippingType::Free => 0,
            ShippingType::FlatRate => $this->rate_cents,
            ShippingType::FreeOverAmount => $subtotalCents >= $this->min_order_cents
                ? 0
                : $this->rate_cents,
        };
    }

    public function estimatedDeliveryLabel(): string
    {
        if ($this->estimated_days_min && $this->estimated_days_max) {
            return "{$this->estimated_days_min}–{$this->estimated_days_max} business days";
        }

        if ($this->estimated_days_max) {
            return "Up to {$this->estimated_days_max} business days";
        }

        return 'Varies';
    }

    protected static function newFactory(): ShippingMethodFactory
    {
        return ShippingMethodFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShippingType::class,
            'rate_cents' => 'integer',
            'min_order_cents' => 'integer',
            'estimated_days_min' => 'integer',
            'estimated_days_max' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
