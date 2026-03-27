<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\ProductDownloadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductDownload extends BaseModel
{
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'user_id',
        'token_hash',
        'expires_at',
        'max_downloads',
        'download_count',
        'last_downloaded_at',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check whether this download link is still usable.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isExhausted();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->download_count >= $this->max_downloads;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProductDownloadFactory
    {
        return ProductDownloadFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'download_count' => 'integer',
            'max_downloads' => 'integer',
        ];
    }
}
