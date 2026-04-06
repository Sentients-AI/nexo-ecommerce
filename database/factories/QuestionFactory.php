<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\Product;
use App\Domain\Question\Models\Question;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<Question>
 */
final class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Context::get('tenant_id') ?? Tenant::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence().'?',
            'is_answered' => false,
        ];
    }

    public function answered(): static
    {
        return $this->state(['is_answered' => true]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
