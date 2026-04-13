<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Actions;

use App\Domain\GiftCard\Models\GiftCard;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final readonly class CreateGiftCardAction
{
    public function execute(
        int $initialBalanceCents,
        ?string $code = null,
        bool $isActive = true,
        ?CarbonInterface $expiresAt = null,
        ?int $createdByUserId = null,
    ): GiftCard {
        $resolvedCode = $code !== null && $code !== ''
            ? mb_strtoupper(mb_trim($code))
            : $this->generateUniqueCode();

        return GiftCard::query()->create([
            'code' => $resolvedCode,
            'initial_balance_cents' => $initialBalanceCents,
            'balance_cents' => $initialBalanceCents,
            'expires_at' => $expiresAt,
            'is_active' => $isActive,
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = mb_strtoupper(Str::random(10));
        } while (GiftCard::query()->where('code', $code)->exists());

        return $code;
    }
}
