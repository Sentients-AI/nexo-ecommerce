<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use App\Domain\Subscription\Enums\BillingInterval;
use App\Domain\Tenant\Models\Tenant;
use App\Shared\Models\BaseModel;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionPlan extends BaseModel
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'billing_interval',
        'price_cents',
        'stripe_price_id',
        'features',
        'is_active',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function formattedPrice(): string
    {
        return '$'.number_format($this->price_cents / 100, 2);
    }

    public function intervalLabel(): string
    {
        return $this->billing_interval === BillingInterval::Annual->value ? 'year' : 'month';
    }

    protected static function newFactory(): SubscriptionPlanFactory
    {
        return SubscriptionPlanFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'price_cents' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
