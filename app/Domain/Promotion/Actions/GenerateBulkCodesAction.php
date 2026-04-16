<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Promotion\Models\Promotion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

final class GenerateBulkCodesAction
{
    /**
     * Generate N unique promo codes cloned from a template promotion.
     *
     * @return Collection<int, Promotion>
     */
    public function execute(Promotion $template, int $count, string $prefix = ''): Collection
    {
        $batchId = Str::uuid()->toString();
        $prefix = mb_strtoupper(mb_trim($prefix));
        $created = collect();

        $base = $template->toArray();
        unset($base['id'], $base['code'], $base['usage_count'], $base['created_at'], $base['updated_at']);

        for ($i = 0; $i < $count; $i++) {
            $created->push($this->createWithUniqueCode($base, $batchId, $prefix));
        }

        return $created;
    }

    private function createWithUniqueCode(array $base, string $batchId, string $prefix): Promotion
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $code = $this->generateCode($prefix);

            try {
                return Promotion::query()->create(array_merge($base, [
                    'code' => $code,
                    'usage_count' => 0,
                    'batch_id' => $batchId,
                    'is_active' => true,
                ]));
            } catch (UniqueConstraintViolationException) {
                if ($attempt === $maxAttempts) {
                    throw new RuntimeException('Failed to generate a unique promo code after '.$maxAttempts.' attempts.');
                }
            }
        }

        // Unreachable — loop always returns or throws.
        throw new RuntimeException('Failed to generate a unique promo code.');
    }

    private function generateCode(string $prefix): string
    {
        $suffix = mb_strtoupper(Str::random(8));

        return $prefix !== '' ? "{$prefix}-{$suffix}" : $suffix;
    }
}
