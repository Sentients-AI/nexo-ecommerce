<?php

declare(strict_types=1);

namespace App\Domain\Projections\Models;

use App\Domain\Refund\Enums\RefundStatus;
use App\Domain\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class RefundProjection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'refund_id',
        'order_id',
        'amount_cents',
        'status',
        'approved_at',
        'succeeded_at',
    ];

    protected $attributes = [
        'status' => RefundStatus::class,
        'approved_at' => null,
        'succeeded_at' => null,
        'amount_cents' => 0.0,
    ];

    public function refundId(): int
    {
        return $this->attributes['refund_id'];
    }
}
