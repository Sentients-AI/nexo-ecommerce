<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Promotion\Models\PromotionUsage;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionUsage>
 */
final class PromotionUsageFactory extends Factory
{
    protected $model = PromotionUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'discount_cents' => $this->faker->numberBetween(500, 5000),
        ];
    }
}
