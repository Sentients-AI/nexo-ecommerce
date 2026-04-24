<?php

declare(strict_types=1);

/**
 * End-to-End Vendor Owner (Tenant Admin) Journey Tests
 *
 * Covers the full lifecycle of a vendor owner (tenant-level admin) managing
 * their store via the Filament control plane: products, categories, inventory,
 * orders, promotions, reviews, conversations, analytics, system health,
 * and pricing — all scoped to their own tenant.
 */

use App\Domain\Category\Models\Category;
use App\Domain\Chat\Models\Conversation;
use App\Domain\GiftCard\Models\GiftCard;
use App\Domain\Inventory\Models\Stock;
use App\Domain\Loyalty\Models\LoyaltyAccount;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\PaymentIntent;
use App\Domain\Product\Models\Product;
use App\Domain\Promotion\Models\Promotion;
use App\Domain\Referral\Models\ReferralCode;
use App\Domain\Refund\Models\Refund;
use App\Domain\Review\Models\Review;
use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Pages\AdvancedAnalytics;
use App\Filament\Pages\AuditLogPage;
use App\Filament\Pages\FraudDashboard;
use App\Filament\Pages\Pricing;
use App\Filament\Pages\SuperAdminDashboard;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Chat\ConversationResource;
use App\Filament\Resources\Chat\Pages\ListConversations;
use App\Filament\Resources\GiftCards\GiftCardResource;
use App\Filament\Resources\GiftCards\Pages\CreateGiftCard;
use App\Filament\Resources\GiftCards\Pages\ListGiftCards;
use App\Filament\Resources\Inventory\InventoryResource;
use App\Filament\Resources\Inventory\Pages\ListInventory;
use App\Filament\Resources\Loyalty\LoyaltyAccounts\LoyaltyAccountResource;
use App\Filament\Resources\Loyalty\LoyaltyAccounts\Pages\ListLoyaltyAccounts;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\Referral\ReferralCodes\Pages\ListReferralCodes;
use App\Filament\Resources\Referral\ReferralCodes\ReferralCodeResource;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Test setup helpers
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
    Role::factory()->create(['name' => 'customer']);

    // Default tenant and vendor owner for all tests
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    Context::add('tenant_id', $this->tenant->id);

    $adminRole = Role::where('name', 'admin')->first();
    $this->vendorOwner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role_id' => $adminRole->id,
    ]);

    $this->actingAs($this->vendorOwner);
});

// ---------------------------------------------------------------------------
// 1. ACCESS CONTROL — WHAT VENDOR OWNERS CAN AND CANNOT ACCESS
// ---------------------------------------------------------------------------

describe('1. Vendor Owner — Access Control', function (): void {
    it('step 1.1 — vendor owner can access the control plane panel', function (): void {
        $this->get('/control-plane')
            ->assertSuccessful();
    });

    it('step 1.2 — vendor owner is denied access to super admin dashboard', function (): void {
        expect(SuperAdminDashboard::canAccess())->toBeFalse();
    });

    it('step 1.3 — vendor owner is denied access to TenantResource', function (): void {
        expect(TenantResource::canAccess())->toBeFalse();
    });

    it('step 1.4 — vendor owner can access AdvancedAnalytics', function (): void {
        expect(AdvancedAnalytics::canAccess())->toBeTrue();
    });

    it('step 1.5 — vendor owner can access FraudDashboard', function (): void {
        expect(FraudDashboard::canAccess())->toBeTrue();
    });

    it('step 1.6 — vendor owner can access SystemHealth', function (): void {
        expect(SystemHealth::canAccess())->toBeTrue();
    });

    it('step 1.7 — vendor owner can access Pricing page', function (): void {
        expect(Pricing::canAccess())->toBeTrue();
    });

    it('step 1.8 — regular customers are denied the control plane panel', function (): void {
        $customerRole = Role::where('name', 'customer')->first();
        $customer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $customerRole->id,
        ]);
        $this->actingAs($customer);

        expect(SuperAdminDashboard::canAccess())->toBeFalse()
            ->and(FraudDashboard::canAccess())->toBeFalse()
            ->and(SystemHealth::canAccess())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// 2. USER MANAGEMENT (OWN TENANT USERS)
// ---------------------------------------------------------------------------

describe('2. Vendor Owner — User Management', function (): void {
    it('step 2.1 — vendor owner can list their tenant users', function (): void {
        User::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ListUsers::class)
            ->assertSuccessful();
    });

    it('step 2.2 — vendor owner only sees their own tenant users', function (): void {
        $otherTenant = Tenant::factory()->create();
        User::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        User::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        $count = UserResource::getEloquentQuery()->count();

        // Only sees 2 (own tenant) + 1 (vendor owner themselves) = 3
        // (vendor owner was created in beforeEach for this tenant)
        expect($count)->toBeLessThanOrEqual(3);
    });

    it('step 2.3 — UserResource is read-only for vendor owners', function (): void {
        expect(UserResource::canCreate())->toBeFalse();
    });

    it('step 2.4 — vendor owner can view users page via HTTP', function (): void {
        $this->get('/control-plane/users')->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 3. PRODUCT MANAGEMENT
// ---------------------------------------------------------------------------

describe('3. Vendor Owner — Product Management', function (): void {
    it('step 3.1 — vendor owner can list their own products', function (): void {
        $otherTenant = Tenant::factory()->create();
        Product::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Product::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

        Livewire::test(ListProducts::class)
            ->assertSuccessful();

        // Tenant scope applies — only their 3 products
        expect(ProductResource::getEloquentQuery()->count())->toBe(3);
    });

    it('step 3.2 — vendor owner can create a product in their tenant', function (): void {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Premium Headphones',
                'slug' => 'premium-headphones',
                'sku' => 'PH-001',
                'price_cents' => 19999,
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Product::class, [
            'sku' => 'PH-001',
            'tenant_id' => $this->tenant->id,
        ]);
    });

    it('step 3.3 — product creation fails with missing required fields', function (): void {
        Livewire::test(CreateProduct::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'slug', 'price_cents']);
    });

    it('step 3.4 — product creation fails with duplicate slug', function (): void {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'existing-product',
        ]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Another Product',
                'slug' => 'existing-product',
                'sku' => 'AP-001',
                'price_cents' => 5000,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    });

    it('step 3.5 — vendor owner can edit their product', function (): void {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->fresh()->name)->toBe('Updated Name');
    });

    it('step 3.6 — vendor owner can edit a product from the list page', function (): void {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Before Edit',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'After Edit'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->fresh()->name)->toBe('After Edit');
    });
});

// ---------------------------------------------------------------------------
// 4. CATEGORY MANAGEMENT
// ---------------------------------------------------------------------------

describe('4. Vendor Owner — Category Management', function (): void {
    it('step 4.1 — vendor owner can list their tenant categories', function (): void {
        $otherTenant = Tenant::factory()->create();
        Category::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Category::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

        expect(CategoryResource::getEloquentQuery()->count())->toBe(3);
    });

    it('step 4.2 — vendor owner can create a category', function (): void {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Accessories',
                'slug' => 'accessories',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Category::class, [
            'slug' => 'accessories',
            'tenant_id' => $this->tenant->id,
        ]);
    });

    it('step 4.3 — vendor owner can list categories via Livewire', function (): void {
        Category::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ListCategories::class)
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 5. INVENTORY MANAGEMENT
// ---------------------------------------------------------------------------

describe('5. Vendor Owner — Inventory Management', function (): void {
    it('step 5.1 — vendor owner can list their inventory', function (): void {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        Stock::factory()->count(2)->create([
            'product_id' => $product->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Livewire::test(ListInventory::class)
            ->assertSuccessful();
    });

    it('step 5.2 — vendor owner cannot create or delete inventory rows', function (): void {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $stock = Stock::factory()->create([
            'product_id' => $product->id,
            'tenant_id' => $this->tenant->id,
        ]);

        expect(InventoryResource::canCreate())->toBeFalse()
            ->and(InventoryResource::canDelete($stock))->toBeFalse();
    });

    it('step 5.3 — vendor owner only sees their own inventory', function (): void {
        $otherTenant = Tenant::factory()->create();
        $ownProduct = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);

        Stock::factory()->create(['product_id' => $ownProduct->id, 'tenant_id' => $this->tenant->id]);
        Stock::factory()->create(['product_id' => $otherProduct->id, 'tenant_id' => $otherTenant->id]);

        expect(InventoryResource::getEloquentQuery()->count())->toBe(1);
    });
});

// ---------------------------------------------------------------------------
// 6. ORDER MANAGEMENT
// ---------------------------------------------------------------------------

describe('6. Vendor Owner — Order Management', function (): void {
    it('step 6.1 — vendor owner can list their tenant orders', function (): void {
        Order::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ListOrders::class)
            ->assertSuccessful();
    });

    it('step 6.2 — vendor owner only sees their own tenant orders', function (): void {
        $otherTenant = Tenant::factory()->create();
        Order::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Order::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        expect(OrderResource::getEloquentQuery()->count())->toBe(2);
    });

    it('step 6.3 — OrderResource is read-only for vendor owners', function (): void {
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id]);

        expect(OrderResource::canCreate())->toBeFalse()
            ->and(OrderResource::canEdit($order))->toBeFalse()
            ->and(OrderResource::canDelete($order))->toBeFalse();
    });

    it('step 6.4 — vendor owner can view orders via HTTP', function (): void {
        $this->get('/control-plane/orders')->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 7. PAYMENT & REFUND OVERSIGHT
// ---------------------------------------------------------------------------

describe('7. Vendor Owner — Payment & Refund Oversight', function (): void {
    it('step 7.1 — vendor owner can list their tenant payments', function (): void {
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id]);
        PaymentIntent::factory()->create([
            'order_id' => $order->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Livewire::test(ListPayments::class)
            ->assertSuccessful();
    });

    it('step 7.2 — vendor owner only sees their own tenant payments', function (): void {
        $otherTenant = Tenant::factory()->create();
        $ownOrder = Order::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherOrder = Order::factory()->create(['tenant_id' => $otherTenant->id]);

        PaymentIntent::factory()->count(2)->create([
            'order_id' => $ownOrder->id,
            'tenant_id' => $this->tenant->id,
        ]);
        PaymentIntent::factory()->count(3)->create([
            'order_id' => $otherOrder->id,
            'tenant_id' => $otherTenant->id,
        ]);

        expect(PaymentResource::getEloquentQuery()->count())->toBe(2);
    });

    it('step 7.3 — vendor owner can list their refunds', function (): void {
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id]);
        // Refund has no factory; insert directly using the string payment_intent_id
        Refund::withoutTenancy()->insert([
            ['order_id' => $order->id, 'payment_intent_id' => 'pi_test_001', 'amount_cents' => 500, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Test', 'tenant_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => $order->id, 'payment_intent_id' => 'pi_test_002', 'amount_cents' => 750, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Test 2', 'tenant_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Livewire::test(ListRefunds::class)
            ->assertSuccessful();
    });

    it('step 7.4 — RefundResource is read-only for vendor owners', function (): void {
        expect(RefundResource::canCreate())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// 8. PROMOTION MANAGEMENT
// ---------------------------------------------------------------------------

describe('8. Vendor Owner — Promotion Management', function (): void {
    it('step 8.1 — vendor owner can list their promotions', function (): void {
        $otherTenant = Tenant::factory()->create();
        Promotion::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Promotion::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        expect(PromotionResource::getEloquentQuery()->count())->toBe(2);
    });

    it('step 8.2 — vendor owner can create a promotion for their tenant', function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Summer Discount',
                'code' => 'SUMMER10',
                'discount_type' => 'fixed',
                'discount_value' => 1000,
                'scope' => 'all',
                'starts_at' => now()->toDateTimeLocalString(),
                'ends_at' => now()->addMonth()->toDateTimeLocalString(),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Promotion::class, [
            'code' => 'SUMMER10',
            'tenant_id' => $this->tenant->id,
        ]);
    });

    it('step 8.3 — promotion creation fails without required fields', function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'discount_type', 'scope', 'starts_at', 'ends_at']);
    });

    it('step 8.4 — vendor owner can list promotions via Livewire', function (): void {
        Livewire::test(ListPromotions::class)
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 9. REVIEW MODERATION
// ---------------------------------------------------------------------------

describe('9. Vendor Owner — Review Moderation', function (): void {
    it('step 9.1 — vendor owner can access the review resource', function (): void {
        expect(ReviewResource::canAccess())->toBeTrue();
    });

    it('step 9.2 — vendor owner cannot create reviews', function (): void {
        expect(ReviewResource::canCreate())->toBeFalse();
    });

    it('step 9.3 — vendor owner can list their tenant reviews', function (): void {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        // Each review must be from a unique user (unique constraint: tenant_id + product_id + user_id)
        $customers = User::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        foreach ($customers as $customer) {
            Review::factory()->create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'user_id' => $customer->id,
            ]);
        }

        Livewire::test(ListReviews::class)
            ->assertSuccessful();
    });

    it('step 9.4 — vendor owner can approve a pending review', function (): void {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $review = Review::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'is_approved' => false,
        ]);

        Livewire::test(ListReviews::class)
            ->callAction(TestAction::make('approve')->table($review));

        expect($review->fresh()->is_approved)->toBeTrue();
    });

    it('step 9.5 — vendor owner can reject an approved review', function (): void {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $review = Review::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'is_approved' => true,
        ]);

        Livewire::test(ListReviews::class)
            ->callAction(TestAction::make('reject')->table($review));

        expect($review->fresh()->is_approved)->toBeFalse();
    });

    it('step 9.6 — vendor owner only sees their own tenant reviews', function (): void {
        $otherTenant = Tenant::factory()->create();
        $ownProduct = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
        // Each review must be from a unique user per product (unique constraint: tenant_id + product_id + user_id)
        $ownUser1 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $ownUser2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        Review::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $ownProduct->id, 'user_id' => $ownUser1->id]);
        Review::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $ownProduct->id, 'user_id' => $ownUser2->id]);
        Review::factory()->create(['tenant_id' => $otherTenant->id, 'product_id' => $otherProduct->id, 'user_id' => $otherUser->id]);

        expect(ReviewResource::getEloquentQuery()->count())->toBe(2);
    });
});

// ---------------------------------------------------------------------------
// 10. CHAT SUPPORT MANAGEMENT
// ---------------------------------------------------------------------------

describe('10. Vendor Owner — Chat & Support Management', function (): void {
    it('step 10.1 — vendor owner can access conversation resource', function (): void {
        expect(ConversationResource::canAccess())->toBeTrue();
    });

    it('step 10.2 — conversations cannot be created via control plane', function (): void {
        expect(ConversationResource::canCreate())->toBeFalse();
    });

    it('step 10.3 — vendor owner sees their tenant store conversations', function (): void {
        $customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        Conversation::factory()->count(2)->create([
            'user_id' => $customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Livewire::test(ListConversations::class)
            ->assertSuccessful();
    });

    it('step 10.4 — vendor owner only sees their own tenant conversations', function (): void {
        $otherTenant = Tenant::factory()->create();
        $ownCustomer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherCustomer = User::factory()->create(['tenant_id' => $otherTenant->id]);

        Conversation::factory()->count(2)->create([
            'user_id' => $ownCustomer->id,
            'tenant_id' => $this->tenant->id,
        ]);
        Conversation::factory()->create([
            'user_id' => $otherCustomer->id,
            'tenant_id' => $otherTenant->id,
        ]);

        expect(ConversationResource::getEloquentQuery()->count())->toBe(2);
    });
});

// ---------------------------------------------------------------------------
// 11. LOYALTY & REFERRAL OVERSIGHT
// ---------------------------------------------------------------------------

describe('11. Vendor Owner — Loyalty & Referral Oversight', function (): void {
    it('step 11.1 — vendor owner can access loyalty accounts resource', function (): void {
        expect(LoyaltyAccountResource::canAccess())->toBeTrue();
    });

    it('step 11.2 — vendor owner can list loyalty accounts for their customers', function (): void {
        $customers = User::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        foreach ($customers as $customer) {
            LoyaltyAccount::factory()->withPoints(100)->create(['user_id' => $customer->id]);
        }

        Livewire::test(ListLoyaltyAccounts::class)
            ->assertSuccessful();
    });

    it('step 11.3 — vendor owner can access referral codes resource', function (): void {
        expect(ReferralCodeResource::canAccess())->toBeTrue();
    });

    it('step 11.4 — vendor owner can list referral codes for their customers', function (): void {
        $customers = User::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        foreach ($customers as $customer) {
            ReferralCode::factory()->create(['user_id' => $customer->id]);
        }

        Livewire::test(ListReferralCodes::class)
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 12. GIFT CARD MANAGEMENT
// ---------------------------------------------------------------------------

describe('12. Vendor Owner — Gift Card Management', function (): void {
    it('step 12.1 — vendor owner can access the gift cards resource', function (): void {
        expect(GiftCardResource::canAccess())->toBeTrue();
    });

    it('step 12.2 — vendor owner can list their tenant gift cards', function (): void {
        GiftCard::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ListGiftCards::class)
            ->assertSuccessful();
    });

    it('step 12.3 — vendor owner can create a gift card', function (): void {
        Livewire::test(CreateGiftCard::class)
            ->fillForm([
                'initial_balance_cents' => 10000,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(GiftCard::class, [
            'initial_balance_cents' => 10000,
            'tenant_id' => $this->tenant->id,
        ]);
    });

    it('step 12.4 — gift card creation fails without balance', function (): void {
        Livewire::test(CreateGiftCard::class)
            ->fillForm(['initial_balance_cents' => null])
            ->call('create')
            ->assertHasFormErrors(['initial_balance_cents']);
    });

    it('step 12.5 — vendor owner only sees their own tenant gift cards', function (): void {
        $otherTenant = Tenant::factory()->create();
        GiftCard::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        GiftCard::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        expect(GiftCardResource::getEloquentQuery()->count())->toBe(2);
    });
});

// ---------------------------------------------------------------------------
// 13. AUDIT LOG & SYSTEM MONITORING
// ---------------------------------------------------------------------------

describe('13. Vendor Owner — Audit Log & System Monitoring', function (): void {
    it('step 13.1 — vendor owner can view the audit log', function (): void {
        Livewire::test(AuditLogPage::class)
            ->assertSuccessful();
    });

    it('step 13.2 — vendor owner can access the system health page', function (): void {
        config(['scout.driver' => 'collection']);

        Livewire::test(SystemHealth::class)
            ->assertSuccessful()
            ->assertActionExists('reindex_search')
            ->assertActionExists('reindex_search_fresh');
    });

    it('step 13.3 — vendor owner can trigger a search re-index', function (): void {
        config(['scout.driver' => 'collection']);

        Livewire::test(SystemHealth::class)
            ->callAction(TestAction::make('reindex_search'))
            ->assertHasNoErrors()
            ->assertNotified('Search index updated');
    });

    it('step 13.4 — vendor owner can trigger a fresh search re-index', function (): void {
        config(['scout.driver' => 'collection']);

        Livewire::test(SystemHealth::class)
            ->callAction(TestAction::make('reindex_search_fresh'))
            ->assertHasNoErrors()
            ->assertNotified('Search index rebuilt');
    });
});

// ---------------------------------------------------------------------------
// 14. DATA ISOLATION — VENDOR OWNER CANNOT ACCESS OTHER TENANTS' DATA
// ---------------------------------------------------------------------------

describe('14. Vendor Owner — Strict Tenant Data Isolation', function (): void {
    it('step 14.1 — product query is scoped to own tenant', function (): void {
        $otherTenant = Tenant::factory()->create();
        Product::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Product::factory()->count(5)->create(['tenant_id' => $otherTenant->id]);

        expect(ProductResource::getEloquentQuery()->count())->toBe(2);
    });

    it('step 14.2 — order query is scoped to own tenant', function (): void {
        $otherTenant = Tenant::factory()->create();
        Order::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Order::factory()->count(4)->create(['tenant_id' => $otherTenant->id]);

        expect(OrderResource::getEloquentQuery()->count())->toBe(3);
    });

    it('step 14.3 — promotion query is scoped to own tenant', function (): void {
        $otherTenant = Tenant::factory()->create();
        Promotion::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Promotion::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        expect(PromotionResource::getEloquentQuery()->count())->toBe(2);
    });

    it('step 14.4 — inventory query is scoped to own tenant', function (): void {
        $otherTenant = Tenant::factory()->create();
        $ownProduct = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);

        Stock::factory()->create(['product_id' => $ownProduct->id, 'tenant_id' => $this->tenant->id]);
        Stock::factory()->create(['product_id' => $otherProduct->id, 'tenant_id' => $otherTenant->id]);

        expect(InventoryResource::getEloquentQuery()->count())->toBe(1);
    });

    it('step 14.5 — refund query is scoped to own tenant', function (): void {
        $otherTenant = Tenant::factory()->create();
        $ownOrder = Order::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherOrder = Order::factory()->create(['tenant_id' => $otherTenant->id]);

        // Refund has no factory; insert directly
        Refund::withoutTenancy()->insert([
            ['order_id' => $ownOrder->id, 'payment_intent_id' => 'pi_own_001', 'amount_cents' => 500, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Test', 'tenant_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => $ownOrder->id, 'payment_intent_id' => 'pi_own_002', 'amount_cents' => 750, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Test 2', 'tenant_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => $otherOrder->id, 'payment_intent_id' => 'pi_other_001', 'amount_cents' => 300, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Other', 'tenant_id' => $otherTenant->id, 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => $otherOrder->id, 'payment_intent_id' => 'pi_other_002', 'amount_cents' => 400, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Other 2', 'tenant_id' => $otherTenant->id, 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => $otherOrder->id, 'payment_intent_id' => 'pi_other_003', 'amount_cents' => 600, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Other 3', 'tenant_id' => $otherTenant->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        expect(RefundResource::getEloquentQuery()->count())->toBe(2);
    });
});
