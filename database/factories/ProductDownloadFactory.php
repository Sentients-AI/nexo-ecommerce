<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductDownload;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * @extends Factory<ProductDownload>
 */
final class ProductDownloadFactory extends Factory
{
    /**
     * @var class-string<Model>
     */
    protected $model = ProductDownload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenantId = Context::get('tenant_id');
        $plain = bin2hex(random_bytes(32));

        return [
            'tenant_id' => $tenantId ?? Tenant::factory(),
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(48),
            'max_downloads' => 5,
            'download_count' => 0,
            'last_downloaded_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): self
    {
        return $this->state(fn (array $attributes): array => [
            'download_count' => 5,
            'max_downloads' => 5,
        ]);
    }
}
