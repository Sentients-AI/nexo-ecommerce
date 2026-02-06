<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Models;

use App\Domain\Order\Models\Order;
use App\Domain\User\Models\User;
use App\Shared\Models\BaseModel;
use Database\Factories\PromotionUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PromotionUsage extends BaseModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'promotion_id',
        'user_id',
        'order_id',
        'discount_cents',
    ];

    /**
     * Get the promotion.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Get the user who used the promotion.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order the promotion was applied to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PromotionUsageFactory
    {
        return PromotionUsageFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_cents' => 'integer',
        ];
    }
}
