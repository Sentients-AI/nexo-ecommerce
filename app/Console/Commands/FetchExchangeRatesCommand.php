<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Currency\Services\CurrencyService;
use Illuminate\Console\Command;

final class FetchExchangeRatesCommand extends Command
{
    protected $signature = 'currency:fetch-rates';

    protected $description = 'Pre-warm the exchange rate cache for all supported currencies';

    public function handle(CurrencyService $currencyService): int
    {
        $supported = config('currency.supported', ['USD', 'MYR', 'EUR']);

        foreach ($supported as $currency) {
            $rates = $currencyService->getRatesFor($currency);
            $count = count($rates);
            $this->line("Fetched {$count} rates for {$currency}");
        }

        $this->info('Exchange rate cache warmed successfully.');

        return self::SUCCESS;
    }
}
