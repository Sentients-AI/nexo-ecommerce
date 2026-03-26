<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Seeder;

final class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Demo tenant 1 - Active subscription
        Tenant::query()->firstOrCreate(
            ['slug' => 'acme-store'],
            [
                'name' => 'ACME Store',
                'email' => 'admin@acme-store.com',
                'is_active' => true,
                'settings' => [
                    'currency' => 'USD',
                    'timezone' => 'America/New_York',
                    'tax_rate' => 8.25,
                ],
                'subscribed_at' => now(),
            ]
        );

        // Demo tenant 2 - On trial
        Tenant::query()->firstOrCreate(
            ['slug' => 'gadget-world'],
            [
                'name' => 'Gadget World',
                'email' => 'admin@gadget-world.com',
                'is_active' => true,
                'settings' => [
                    'currency' => 'MYR',
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'tax_rate' => 6,
                ],
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        // Demo tenant 3 - Active with custom domain
        Tenant::firstOrCreate(
            ['slug' => 'fashion-hub'],
            [
                'name' => 'Fashion Hub',
                'email' => 'admin@fashionhub.com',
                'domain' => 'shop.fashionhub.com',
                'is_active' => true,
                'settings' => [
                    'currency' => 'EUR',
                    'timezone' => 'Europe/London',
                    'tax_rate' => 20,
                ],
                'subscribed_at' => now()->subMonths(3),
            ]
        );

        // Demo tenant 4 - Inactive
        Tenant::firstOrCreate(
            ['slug' => 'old-shop'],
            [
                'name' => 'Old Shop (Inactive)',
                'email' => 'admin@old-shop.com',
                'is_active' => false,
                'settings' => [
                    'currency' => 'USD',
                    'timezone' => 'America/Los_Angeles',
                    'tax_rate' => 7.5,
                ],
            ]
        );
    }
}
