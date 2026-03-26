<?php

declare(strict_types=1);

use App\Domain\Currency\Services\CurrencyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

describe('CurrencyService', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('returns 1.0 for same currency', function () {
        $service = app(CurrencyService::class);

        expect($service->getRate('USD', 'USD'))->toBe(1.0)
            ->and($service->getRate('myr', 'MYR'))->toBe(1.0);
    });

    it('returns same amount when converting same currency', function () {
        $service = app(CurrencyService::class);

        expect($service->convertCents(1000, 'MYR', 'MYR'))->toBe(1000)
            ->and($service->convertCents(5000, 'usd', 'USD'))->toBe(5000);
    });

    it('fetches rate from API and caches it', function () {
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'MYR',
                'rates' => ['USD' => 0.22, 'EUR' => 0.21],
            ]),
        ]);

        $service = app(CurrencyService::class);
        $rate = $service->getRate('MYR', 'USD');

        expect($rate)->toBe(0.22);

        // Second call should use cache, not make another HTTP request
        Http::fake([
            '*/latest*' => Http::response(['base' => 'MYR', 'rates' => ['USD' => 0.99]]),
        ]);

        $cachedRate = $service->getRate('MYR', 'USD');
        expect($cachedRate)->toBe(0.22); // still the cached value
    });

    it('converts cents between currencies using the exchange rate', function () {
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'MYR',
                'rates' => ['USD' => 0.22],
            ]),
        ]);

        $service = app(CurrencyService::class);

        // 1000 MYR cents * 0.22 = 220 USD cents
        expect($service->convertCents(1000, 'MYR', 'USD'))->toBe(220);
    });

    it('rounds converted cents to nearest integer', function () {
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'MYR',
                'rates' => ['USD' => 0.225],
            ]),
        ]);

        $service = app(CurrencyService::class);

        // 1000 * 0.225 = 225.0 → 225
        expect($service->convertCents(1000, 'MYR', 'USD'))->toBe(225);

        // 3 * 0.225 = 0.675 → rounds to 1
        expect($service->convertCents(3, 'MYR', 'USD'))->toBe(1);
    });

    it('returns 1.0 fallback rate when API fails', function () {
        Http::fake([
            '*/latest*' => Http::response([], 500),
        ]);

        $service = app(CurrencyService::class);

        expect($service->getRate('MYR', 'USD'))->toBe(1.0);
    });

    it('returns same amount when API fails on convertCents', function () {
        Http::fake([
            '*/latest*' => Http::response([], 500),
        ]);

        $service = app(CurrencyService::class);

        // Falls back to rate 1.0, so conversion returns original amount
        expect($service->convertCents(1000, 'MYR', 'USD'))->toBe(1000);
    });

    it('returns 1.0 when rate not found in response', function () {
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'MYR',
                'rates' => ['EUR' => 0.21],
            ]),
        ]);

        $service = app(CurrencyService::class);

        expect($service->getRate('MYR', 'JPY'))->toBe(1.0);
    });
});
