<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Actions;

use App\Domain\Promotion\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
            $code = $this->generateUniqueCode($prefix);

            $promo = Promotion::query()->create(array_merge($base, [
                'code' => $code,
                'usage_count' => 0,
                'batch_id' => $batchId,
                'is_active' => true,
            ]));

            $created->push($promo);
        }

        return $created;
    }

    private function generateUniqueCode(string $prefix): string
    {
        do {
            $suffix = mb_strtoupper(Str::random(8));
            $code = $prefix !== '' ? "{$prefix}-{$suffix}" : $suffix;
        } while (Promotion::query()->where('code', $code)->exists());

        return $code;
    }
}
