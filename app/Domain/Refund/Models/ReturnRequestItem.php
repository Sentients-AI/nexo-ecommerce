<?php

declare(strict_types=1);

namespace App\Domain\Refund\Models;

use App\Domain\Order\Models\OrderItem;
use App\Domain\Refund\Enums\ReturnReason;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReturnRequestItem extends BaseModel
{
    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'reason',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reason' => ReturnReason::class,
        ];
    }
}
