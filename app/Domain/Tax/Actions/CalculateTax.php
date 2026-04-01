<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\DTOs\TaxCalculationData;
use App\Domain\Tax\Models\TaxZone;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

final class CalculateTax
{
    public function execute(TaxCalculationData $data): int
    {
        if (! config('tax.enabled')) {
            return 0;
        }

        $rate = $this->resolveRate($data->countryCode, $data->regionCode);

        return (int) round($data->subtotalCents * $rate);
    }

    private function resolveRate(?string $countryCode, ?string $regionCode): float
    {
        $tenantId = Context::get('tenant_id');

        // Build candidate queries from most-specific to least-specific
        $candidates = $this->buildCandidates($tenantId, $countryCode, $regionCode);

        foreach ($candidates as $candidate) {
            $zone = TaxZone::query()
                ->withoutTenancy()
                ->where('is_active', true)
                ->when(
                    array_key_exists('tenant_id', $candidate),
                    fn ($q) => $candidate['tenant_id'] === null
                        ? $q->whereNull('tenant_id')
                        : $q->where('tenant_id', $candidate['tenant_id'])
                )
                ->when(
                    array_key_exists('country_code', $candidate),
                    fn ($q) => $candidate['country_code'] === null
                        ? $q->whereNull('country_code')
                        : $q->where('country_code', $candidate['country_code'])
                )
                ->when(
                    array_key_exists('region_code', $candidate),
                    fn ($q) => $candidate['region_code'] === null
                        ? $q->whereNull('region_code')
                        : $q->where('region_code', $candidate['region_code'])
                )
                ->first();

            if ($zone !== null) {
                return (float) $zone->rate;
            }
        }

        // Final fallback to global config rate — log so operators notice if rate is zero
        $configRate = (float) config('tax.rate', 0);

        Log::warning('Tax calculation fell back to config rate — no matching tax zone found.', [
            'tenant_id' => Context::get('tenant_id'),
            'country_code' => $countryCode,
            'region_code' => $regionCode,
            'config_rate' => $configRate,
        ]);

        return $configRate;
    }

    /**
     * Returns candidates ordered from most-specific to least-specific.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCandidates(?int $tenantId, ?string $countryCode, ?string $regionCode): array
    {
        $candidates = [];

        if ($tenantId !== null) {
            // Tenant-specific: region > country > global
            if ($countryCode !== null && $regionCode !== null) {
                $candidates[] = ['tenant_id' => $tenantId, 'country_code' => $countryCode, 'region_code' => $regionCode];
            }
            if ($countryCode !== null) {
                $candidates[] = ['tenant_id' => $tenantId, 'country_code' => $countryCode, 'region_code' => null];
            }
            $candidates[] = ['tenant_id' => $tenantId, 'country_code' => null, 'region_code' => null];
        }

        // Cross-tenant (null tenant_id = platform-wide zone)
        if ($countryCode !== null && $regionCode !== null) {
            $candidates[] = ['tenant_id' => null, 'country_code' => $countryCode, 'region_code' => $regionCode];
        }
        if ($countryCode !== null) {
            $candidates[] = ['tenant_id' => null, 'country_code' => $countryCode, 'region_code' => null];
        }
        $candidates[] = ['tenant_id' => null, 'country_code' => null, 'region_code' => null];

        return $candidates;
    }
}
