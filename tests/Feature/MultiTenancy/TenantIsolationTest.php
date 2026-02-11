<?php

declare(strict_types=1);

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;
use Tests\Traits\WithTenant;

uses(TestCase::class, RefreshDatabase::class, WithTenant::class);

describe('Tenant Isolation', function (): void {
    beforeEach(function (): void {
        $this->setUpTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    });

    it('creates products scoped to current tenant', function (): void {
        $category = Category::factory()->forTenant($this->tenant)->create();
        $product = Product::factory()->forTenant($this->tenant)->create([
            'category_id' => $category->id,
        ]);

        expect($product->tenant_id)->toBe($this->tenant->id);
    });

    it('automatically filters products by tenant', function (): void {
        // Create product for current tenant
        $category = Category::factory()->forTenant($this->tenant)->create();
        $product1 = Product::factory()->forTenant($this->tenant)->create([
            'category_id' => $category->id,
        ]);

        // Create product for different tenant
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
        $categoryB = Category::factory()->forTenant($tenantB)->create();
        $product2 = Product::factory()->forTenant($tenantB)->create([
            'category_id' => $categoryB->id,
        ]);

        // Query should only return products for current tenant
        $products = Product::all();

        expect($products)->toHaveCount(1)
            ->and($products->first()->id)->toBe($product1->id);
    });

    it('allows bypassing tenant scope with withoutTenancy', function (): void {
        // Create product for current tenant
        $category = Category::factory()->forTenant($this->tenant)->create();
        Product::factory()->forTenant($this->tenant)->create([
            'category_id' => $category->id,
        ]);

        // Create product for different tenant
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
        $categoryB = Category::factory()->forTenant($tenantB)->create();
        Product::factory()->forTenant($tenantB)->create([
            'category_id' => $categoryB->id,
        ]);

        // Without tenancy scope, should return all products
        $products = Product::query()->withoutTenancy()->get();

        expect($products)->toHaveCount(2);
    });

    it('isolates users by tenant', function (): void {
        // Create users for current tenant
        $user1 = User::factory()->forTenant($this->tenant)->create();

        // Create user for different tenant
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
        User::factory()->forTenant($tenantB)->create();

        // Query should only return users for current tenant
        $users = User::all();

        expect($users)->toHaveCount(1)
            ->and($users->first()->id)->toBe($user1->id);
    });

    it('isolates categories by tenant', function (): void {
        // Create category for current tenant
        $category1 = Category::factory()->forTenant($this->tenant)->create();

        // Create category for different tenant
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
        Category::factory()->forTenant($tenantB)->create();

        // Query should only return categories for current tenant
        $categories = Category::all();

        expect($categories)->toHaveCount(1)
            ->and($categories->first()->id)->toBe($category1->id);
    });

    it('automatically sets tenant_id when creating models', function (): void {
        // Create product without explicitly setting tenant_id
        $category = Category::factory()->forTenant($this->tenant)->create();
        $product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price_cents' => 1000,
            'category_id' => $category->id,
        ]);

        expect($product->tenant_id)->toBe($this->tenant->id);
    });
});

describe('Tenant Context Switching', function (): void {
    it('respects context changes', function (): void {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        // Set context to tenant A
        Context::add('tenant_id', $tenantA->id);

        // Create product for tenant A
        $categoryA = Category::factory()->forTenant($tenantA)->create();
        $productA = Product::factory()->forTenant($tenantA)->create([
            'category_id' => $categoryA->id,
        ]);

        // Switch context to tenant B
        Context::forget('tenant_id');
        Context::add('tenant_id', $tenantB->id);

        // Create product for tenant B
        $categoryB = Category::factory()->forTenant($tenantB)->create();
        $productB = Product::factory()->forTenant($tenantB)->create([
            'category_id' => $categoryB->id,
        ]);

        // Query should return only tenant B products
        $products = Product::all();

        expect($products)->toHaveCount(1)
            ->and($products->first()->id)->toBe($productB->id);

        // Switch back to tenant A
        Context::forget('tenant_id');
        Context::add('tenant_id', $tenantA->id);

        // Query should return only tenant A products
        $products = Product::all();

        expect($products)->toHaveCount(1)
            ->and($products->first()->id)->toBe($productA->id);
    });
});

describe('Super Admin Access', function (): void {
    it('creates super admin users without tenant', function (): void {
        $superAdmin = User::factory()->superAdmin()->create();

        expect($superAdmin->tenant_id)->toBeNull()
            ->and($superAdmin->isSuperAdmin())->toBeFalse(); // Needs super_admin role too
    });
});
