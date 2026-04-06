<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\EarningStatus;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\VendorEarningFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VendorEarning extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'gross_amount_cents',
        'platform_fee_cents',
        'net_amount_cents',
        'refunded_amount_cents',
        'status',
        'available_at',
        'paid_out_at',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === EarningStatus::Available;
    }

    public function effectiveNetCents(): int
    {
        return $this->net_amount_cents - $this->refunded_amount_cents;
    }

    protected static function newFactory(): VendorEarningFactory
    {
        return VendorEarningFactory::new();
    }

    protected function casts(): array
    {
        return [
            'gross_amount_cents' => 'integer',
            'platform_fee_cents' => 'integer',
            'net_amount_cents' => 'integer',
            'refunded_amount_cents' => 'integer',
            'status' => EarningStatus::class,
            'available_at' => 'datetime',
            'paid_out_at' => 'datetime',
        ];
    }
}
