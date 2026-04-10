<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Bundle\Models\Bundle;
use App\Domain\Bundle\Models\BundleItem;
use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BundleItem>
 */
final class BundleItemFactory extends Factory
{
    protected $model = BundleItem::class;

    public function definition(): array
    {
        return [
            'bundle_id' => Bundle::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }
}
