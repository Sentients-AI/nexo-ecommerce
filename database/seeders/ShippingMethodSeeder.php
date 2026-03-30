<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Shipping\Enums\ShippingType;
use App\Domain\Shipping\Models\ShippingMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class ShippingMethodSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $methods = [
            [
                'name' => 'Standard Shipping',
                'description' => 'Delivered to your door in 5–7 business days.',
                'type' => ShippingType::FlatRate,
                'rate_cents' => 599,
                'min_order_cents' => null,
                'estimated_days_min' => 5,
                'estimated_days_max' => 7,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Express Shipping',
                'description' => 'Priority delivery in 1–3 business days.',
                'type' => ShippingType::FlatRate,
                'rate_cents' => 1499,
                'min_order_cents' => null,
                'estimated_days_min' => 1,
                'estimated_days_max' => 3,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Free Shipping',
                'description' => 'Free on orders over $100.',
                'type' => ShippingType::FreeOverAmount,
                'rate_cents' => 599,
                'min_order_cents' => 10000,
                'estimated_days_min' => 5,
                'estimated_days_max' => 10,
                'is_active' => true,
                'sort_order' => 0,
            ],
        ];

        foreach ($methods as $method) {
            ShippingMethod::query()->firstOrCreate(
                ['name' => $method['name'], 'tenant_id' => null],
                $method
            );
        }
    }
}
