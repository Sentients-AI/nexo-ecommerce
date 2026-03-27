<?php

declare(strict_types=1);

use App\Domain\Product\Actions\CreateProductVariant;
use App\Domain\Product\DTOs\ProductVariantData;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\VariantAttributeType;
use App\Domain\Product\Models\VariantAttributeValue;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

beforeEach(function () {
    $this->setUpTenant();
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->product = Product::factory()->create(['is_active' => true]);

    $this->colorType = VariantAttributeType::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Color',
        'slug' => 'color',
    ]);

    $this->sizeType = VariantAttributeType::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Size',
        'slug' => 'size',
    ]);

    $this->redValue = VariantAttributeValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'attribute_type_id' => $this->colorType->id,
        'value' => 'Red',
        'slug' => 'red',
        'metadata' => ['hex' => '#FF0000'],
    ]);

    $this->blueValue = VariantAttributeValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'attribute_type_id' => $this->colorType->id,
        'value' => 'Blue',
        'slug' => 'blue',
        'metadata' => ['hex' => '#0000FF'],
    ]);

    $this->smallValue = VariantAttributeValue::factory()->create([
        'tenant_id' => $this->tenant->id,
        'attribute_type_id' => $this->sizeType->id,
        'value' => 'Small',
        'slug' => 'small',
    ]);
});

describe('CreateProductVariant action', function () {
    it('creates a variant with attribute values and a stock record', function () {
        $action = app(CreateProductVariant::class);

        $variant = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'VAR-RED-S-001',
            attributeValueIds: [$this->redValue->id, $this->smallValue->id],
            priceCents: '1500',
        ));

        expect($variant->sku)->toBe('VAR-RED-S-001')
            ->and((int) $variant->price_cents)->toBe(1500)
            ->and((int) $variant->product_id)->toBe($this->product->id)
            ->and($variant->attributeValues)->toHaveCount(2)
            ->and($variant->stock)->not->toBeNull()
            ->and($variant->stock->quantity_available)->toBe(0);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'VAR-RED-S-001',
            'product_id' => $this->product->id,
        ]);

        $this->assertDatabaseHas('product_variant_attribute_values', [
            'product_variant_id' => $variant->id,
            'attribute_value_id' => $this->redValue->id,
        ]);
    });

    it('inherits product price when variant price_cents is null', function () {
        $action = app(CreateProductVariant::class);

        $variant = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'VAR-INHERIT-PRICE',
            attributeValueIds: [$this->blueValue->id],
        ));

        expect($variant->price_cents)->toBeNull()
            ->and($variant->effective_price)->toBe($this->product->price_cents);
    });
});

describe('Cart API with variants', function () {
    it('adds a product with a variant to the cart', function () {
        $action = app(CreateProductVariant::class);
        $variant = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'CART-VAR-001',
            attributeValueIds: [$this->redValue->id, $this->smallValue->id],
            priceCents: '2000',
        ));

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('cart.items.0.variant_id', $variant->id)
            ->assertJsonPath('cart.items.0.price', 2000)
            ->assertJsonPath('cart.items.0.variant.sku', 'CART-VAR-001');
    });

    it('uses variant price when adding to cart', function () {
        $action = app(CreateProductVariant::class);
        $variant = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'PRICE-VAR-001',
            attributeValueIds: [$this->blueValue->id],
            priceCents: '9999',
        ));

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('cart.items.0.price', 9999)
            ->assertJsonPath('cart.items.0.quantity', 2);
    });

    it('treats the same product with different variants as separate cart items', function () {
        $this->actingAsUserInTenant();

        $action = app(CreateProductVariant::class);

        $variantA = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'SEP-VAR-RED',
            attributeValueIds: [$this->redValue->id],
        ));

        $variantB = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'SEP-VAR-BLUE',
            attributeValueIds: [$this->blueValue->id],
        ));

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $variantA->id,
            'quantity' => 1,
        ])->assertOk();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $variantB->id,
            'quantity' => 1,
        ])->assertOk();

        expect($response->json('cart.items'))->toHaveCount(2);
    });

    it('increments quantity when the same product and variant is added again', function () {
        $this->actingAsUserInTenant();

        $action = app(CreateProductVariant::class);
        $variant = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'INCR-VAR-001',
            attributeValueIds: [$this->redValue->id],
        ));

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        expect($response->json('cart.items'))->toHaveCount(1)
            ->and($response->json('cart.items.0.quantity'))->toBe(3);
    });

    it('rejects a variant_id that does not belong to the product', function () {
        $otherProduct = Product::factory()->create(['is_active' => true]);
        $action = app(CreateProductVariant::class);
        $otherVariant = $action->execute(new ProductVariantData(
            productId: (string) $otherProduct->id,
            sku: 'OTHER-PRODUCT-VAR',
            attributeValueIds: [],
        ));

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $otherVariant->id,
            'quantity' => 1,
        ]);

        $response->assertNotFound();
    });

    it('falls back to product price when variant has no price override', function () {
        $action = app(CreateProductVariant::class);
        $variant = $action->execute(new ProductVariantData(
            productId: (string) $this->product->id,
            sku: 'NO-PRICE-VAR',
            attributeValueIds: [],
        ));

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $expectedPrice = (int) ($this->product->sale_price ?? $this->product->price_cents);
        expect($response->json('cart.items.0.price'))->toBe($expectedPrice);
    });
});
