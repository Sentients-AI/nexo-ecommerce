<?php

declare(strict_types=1);

namespace App\Domain\Projections\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class OrderFinancialProjection extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'order_id';

    protected $fillable = [
        'order_id',
        'total_amount',
        'paid_amount',
        'refunded_amount',
        'refund_status',
    ];

    protected $attributes = [

    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'total_amount' => 'integer',
            'paid_amount' => 'integer',
            'refunded_amount' => 'integer',
            'refund_status' => 'string',
        ];
    }
}
