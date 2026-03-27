<?php

declare(strict_types=1);

namespace App\Domain\Currency\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CurrencyService
{
    /**
     * Get the exchange rate multiplier from one currency to another.
     * Returns 1.0 if the currencies are the same or if the API call fails.
     */
    public function getRate(string $from, string $to): float
    {
        $from = mb_strtoupper($from);
        $to = mb_strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->getRatesFor($from);

        return $rates[$to] ?? 1.0;
    }

    /**
     * Convert an amount in cents from one currency to another.
     * Returns the converted amount in cents, rounded to the nearest integer.
     */
    public function convertCents(int $amountCents, string $from, string $to): int
    {
        if (mb_strtoupper($from) === mb_strtoupper($to)) {
            return $amountCents;
        }

        $rate = $this->getRate($from, $to);

        return (int) round($amountCents * $rate);
    }

    /**
     * Fetch and cache all exchange rates for the given base currency.
     *
     * On API failure, the last known rates are served from a long-lived stale cache
     * rather than falling back to an empty array (which would produce incorrect 1.0 rates).
     *
     * @return array<string, float>
     */
    public function getRatesFor(string $baseCurrency): array
    {
        $baseCurrency = mb_strtoupper($baseCurrency);
        $cacheKey = "currency_rates_{$baseCurrency}";
        $staleCacheKey = "currency_rates_{$baseCurrency}_stale";
        $ttl = config('currency.cache_ttl', 3600);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $fresh = $this->fetchFromApi($baseCurrency);

        if ($fresh !== []) {
            Cache::put($cacheKey, $fresh, $ttl);
            Cache::put($staleCacheKey, $fresh, $ttl * 24);

            return $fresh;
        }

        // API failed — serve last known rates to avoid silent 1.0 fallback
        $stale = Cache::get($staleCacheKey);

        if ($stale !== null) {
            Log::warning('CurrencyService: Serving stale exchange rates after API failure', [
                'base' => $baseCurrency,
            ]);

            return $stale;
        }

        return [];
    }

    /**
     * @return array<string, float>
     */
    private function fetchFromApi(string $baseCurrency): array
    {
        try {
            $apiUrl = config('currency.api_url', 'https://api.frankfurter.app');
            $response = Http::timeout(5)->get("{$apiUrl}/latest", [
                'from' => $baseCurrency,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['rates'] ?? [];
            }

            Log::warning('CurrencyService: API returned non-200 response', [
                'status' => $response->status(),
                'base' => $baseCurrency,
            ]);
        } catch (Throwable $e) {
            Log::warning('CurrencyService: Failed to fetch exchange rates', [
                'base' => $baseCurrency,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
}
