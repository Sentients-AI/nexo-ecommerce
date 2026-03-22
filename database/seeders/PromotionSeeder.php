<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Promotion\Models\Promotion;
use Illuminate\Database\Seeder;

final class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Active percentage discount - sitewide
        Promotion::factory()->percentage(1000)->create([
            'name' => 'Welcome 10% Off',
            'code' => 'WELCOME10',
            'description' => 'Get 10% off your first order.',
            'usage_limit' => 500,
            'per_user_limit' => 1,
        ]);

        // Active fixed discount - sitewide
        Promotion::factory()->fixed(1500)->create([
            'name' => '$15 Off Orders Over $75',
            'code' => 'SAVE15',
            'description' => 'Save $15 on orders of $75 or more.',
            'minimum_order_cents' => 7500,
            'usage_limit' => 200,
        ]);

        // Summer sale - 20% off with max cap
        Promotion::factory()->percentage(2000)->withMaxDiscount(5000)->create([
            'name' => 'Summer Sale 20% Off',
            'code' => 'SUMMER20',
            'description' => 'Enjoy 20% off this summer, up to $50 discount.',
            'usage_limit' => 1000,
        ]);

        // Auto-apply flash deal
        Promotion::factory()->percentage(500)->autoApply()->create([
            'name' => 'Flash Deal 5% Off',
            'description' => 'Automatic 5% discount applied at checkout.',
        ]);

        // Free shipping / big fixed discount
        Promotion::factory()->fixed(500)->create([
            'name' => '$5 Off Any Order',
            'code' => 'FIVEOFF',
            'description' => 'Take $5 off any order.',
        ]);

        // Expired promo (for historical reference)
        Promotion::factory()->percentage(1500)->expired()->create([
            'name' => 'Black Friday 15% Off',
            'code' => 'BLACKFRI15',
            'description' => 'Black Friday special — 15% off everything.',
            'usage_limit' => 300,
            'usage_count' => 287,
        ]);

        // Inactive promo (draft)
        Promotion::factory()->fixed(2000)->inactive()->create([
            'name' => '$20 Off Holiday Special',
            'code' => 'HOLIDAY20',
            'description' => 'Holiday special — $20 off orders over $100.',
            'minimum_order_cents' => 10000,
        ]);
    }
}
