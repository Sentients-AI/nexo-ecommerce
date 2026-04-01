<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\WaitlistSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WaitlistSubscription extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'product_id',
        'tenant_id',
        'email',
        'notified_at',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): WaitlistSubscriptionFactory
    {
        return WaitlistSubscriptionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }
}
