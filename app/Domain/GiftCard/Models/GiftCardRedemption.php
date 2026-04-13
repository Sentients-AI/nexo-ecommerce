<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GiftCardRedemption extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'gift_card_id',
        'order_id',
        'amount_cents',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }
}
