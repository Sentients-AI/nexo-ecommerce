<?php

declare(strict_types=1);

namespace App\Domain\Refund\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Refund\Enums\ReturnStatus;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ReturnRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReturnRequest extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'user_id',
        'status',
        'notes',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'refund_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === ReturnStatus::Pending;
    }

    public function totalRefundCents(): int
    {
        return $this->items->sum(
            fn (ReturnRequestItem $item) => $item->orderItem->price_cents_snapshot * $item->quantity
        );
    }

    protected static function newFactory(): ReturnRequestFactory
    {
        return ReturnRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
