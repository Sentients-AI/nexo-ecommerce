<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Models;

use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\Carbon;
use Database\Factories\Domain\GiftCard\GiftCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GiftCard extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'code',
        'initial_balance_cents',
        'balance_cents',
        'expires_at',
        'is_active',
        'created_by_user_id',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->balance_cents <= 0) {
            return false;
        }

        if ($this->expires_at !== null && Carbon::now()->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    public function deductBalance(int $amountCents): void
    {
        $this->decrement('balance_cents', $amountCents);
    }

    protected static function newFactory(): GiftCardFactory
    {
        return GiftCardFactory::new();
    }

    protected function casts(): array
    {
        return [
            'initial_balance_cents' => 'integer',
            'balance_cents' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
