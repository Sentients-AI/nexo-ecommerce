<?php

declare(strict_types=1);

/**
 * End-to-End Super Admin Journey Tests
 *
 * Covers the full lifecycle of a super admin (platform administrator) managing
 * the control plane: tenant management, cross-tenant data, system configuration,
 * promotions, reviews, conversations, loyalty, referrals, and security monitoring.
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
use App\Filament\Pages\Anomalies;
use App\Filament\Pages\AuditLogPage;
use App\Filament\Pages\FraudDashboard;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\SuperAdminDashboard;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Chat\ConversationResource;
use App\Filament\Resources\Chat\Pages\ListConversations;
use App\Filament\Resources\FeatureFlags\FeatureFlagResource;
use App\Filament\Resources\FeatureFlags\Pages\ListFeatureFlags;
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
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Livewire\TenantSwitcher;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

function createSuperAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin']);

    return User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
}

function createAdminForTenant(Tenant $tenant): User
{
    $role = Role::firstOrCreate(['name' => 'admin']);

    return User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);
}

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
    Role::factory()->create(['name' => 'customer']);
});

// ---------------------------------------------------------------------------
// 1. AUTHENTICATION & ACCESS CONTROL
// ---------------------------------------------------------------------------

describe('1. Super Admin Authentication & Access Control', function (): void {
    it('step 1.1 — can access the control plane login page', function (): void {
        $this->get('/control-plane/login')
            ->assertOk()
            ->assertSee('Control Plane');
    });

    it('step 1.2 — super admin dashboard is accessible to super admins', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(SuperAdminDashboard::canAccess())->toBeTrue();
    });

    it('step 1.3 — super admin dashboard is denied to regular admins', function (): void {
        $tenant = Tenant::factory()->create();
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        expect(SuperAdminDashboard::canAccess())->toBeFalse();
    });

    it('step 1.4 — super admin dashboard is denied to unauthenticated users', function (): void {
        expect(SuperAdminDashboard::canAccess())->toBeFalse();
    });

    it('step 1.5 — super admin can access the control plane panel', function (): void {
        $superAdmin = createSuperAdmin();

        $this->actingAs($superAdmin)
            ->get('/control-plane')
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 2. TENANT MANAGEMENT
// ---------------------------------------------------------------------------

describe('2. Super Admin — Tenant Management', function (): void {
    it('step 2.1 — TenantResource is only accessible to super admins', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(TenantResource::canAccess())->toBeTrue();
    });

    it('step 2.2 — TenantResource is denied to tenant admins', function (): void {
        $tenant = Tenant::factory()->create();
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        expect(TenantResource::canAccess())->toBeFalse();
    });

    it('step 2.3 — super admin can list all tenants', function (): void {
        Tenant::factory()->count(3)->create();
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListTenants::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Tenant::all());
    });

    it('step 2.4 — super admin can create a new tenant', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'New Store Co.',
                'slug' => 'new-store-co',
                'email' => 'contact@newstore.co',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Tenant::class, ['slug' => 'new-store-co']);
    });

    it('step 2.5 — tenant creation fails with duplicate slug', function (): void {
        $existing = Tenant::factory()->create(['slug' => 'existing-store']);
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Another Store',
                'slug' => 'existing-store',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    });

    it('step 2.6 — super admin can switch to a tenant context via viewAs action', function (): void {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListTenants::class)
            ->callAction(TestAction::make('viewAs')->table($tenant))
            ->assertHasNoErrors();
    });

    it('step 2.7 — tenant switcher renders for super admin with all active tenants', function (): void {
        $superAdmin = createSuperAdmin();
        Tenant::factory()->create(['name' => 'Visible Tenant', 'is_active' => true]);
        Tenant::factory()->create(['name' => 'Hidden Tenant', 'is_active' => false]);
        $this->actingAs($superAdmin);

        Livewire::test(TenantSwitcher::class)
            ->assertSuccessful()
            ->assertSee('All Tenants')
            ->assertSee('Visible Tenant')
            ->assertDontSee('Hidden Tenant');
    });

    it('step 2.8 — super admin can select a tenant context via session', function (): void {
        $tenant = Tenant::factory()->create(['name' => 'Selected Store', 'is_active' => true]);
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        session(['filament_selected_tenant_id' => $tenant->id]);

        Livewire::test(TenantSwitcher::class)
            ->assertSee('Selected Store');
    });
});

// ---------------------------------------------------------------------------
// 3. USER MANAGEMENT
// ---------------------------------------------------------------------------

describe('3. Admin — User Management', function (): void {
    it('step 3.1 — tenant admin can list users in their tenant', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        User::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertSuccessful();
    });

    it('step 3.2 — UserResource cannot create or edit users', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        expect(UserResource::canCreate())->toBeFalse();
    });

    it('step 3.3 — tenant admin can view the users list page via HTTP', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);

        $this->actingAs($admin)
            ->get('/control-plane/users')
            ->assertSuccessful();
    });

    it('step 3.4 — tenant admin can access the user list (for their tenant)', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        expect(UserResource::canAccess())->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// 4. ORDER MANAGEMENT
// ---------------------------------------------------------------------------

describe('4. Admin — Order Management', function (): void {
    it('step 4.1 — tenant admin can list orders in their tenant', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        Order::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListOrders::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Order::all());
    });

    it('step 4.2 — OrderResource disallows creation, editing, and deletion', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($admin);

        expect(OrderResource::canCreate())->toBeFalse()
            ->and(OrderResource::canEdit($order))->toBeFalse()
            ->and(OrderResource::canDelete($order))->toBeFalse();
    });

    it('step 4.3 — tenant admin can view the orders page via HTTP', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);

        $this->actingAs($admin)
            ->get('/control-plane/orders')
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 5. PAYMENT MANAGEMENT
// ---------------------------------------------------------------------------

describe('5. Super Admin — Payment Management', function (): void {
    it('step 5.1 — super admin can list all payments', function (): void {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        PaymentIntent::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
        ]);
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListPayments::class)
            ->assertSuccessful();
    });

    it('step 5.2 — super admin can view payments via HTTP', function (): void {
        $superAdmin = createSuperAdmin();

        $this->actingAs($superAdmin)
            ->get('/control-plane/payments')
            ->assertSuccessful();
    });

    it('step 5.3 — PaymentResource is read-only', function (): void {
        $superAdmin = createSuperAdmin();
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $payment = PaymentIntent::factory()->create(['order_id' => $order->id, 'tenant_id' => $tenant->id]);
        $this->actingAs($superAdmin);

        expect(PaymentResource::canCreate())->toBeFalse()
            ->and(PaymentResource::canEdit($payment))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// 6. REFUND MANAGEMENT
// ---------------------------------------------------------------------------

describe('6. Admin — Refund Management', function (): void {
    it('step 6.1 — tenant admin can list refunds in their tenant', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        // Refund has no factory; insert directly using the string payment_intent_id
        Refund::withoutTenancy()->insert([
            ['order_id' => $order->id, 'payment_intent_id' => 'pi_test_001', 'amount_cents' => 500, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Test', 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => $order->id, 'payment_intent_id' => 'pi_test_002', 'amount_cents' => 750, 'currency' => 'USD', 'status' => 'requested', 'reason' => 'Test 2', 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListRefunds::class)
            ->assertSuccessful();
    });

    it('step 6.2 — RefundResource is read-only', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        expect(RefundResource::canCreate())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// 7. PRODUCT & CATALOG MANAGEMENT
// ---------------------------------------------------------------------------

describe('7. Admin — Product & Catalog Management', function (): void {
    it('step 7.1 — tenant admin can list products in their tenant', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        Product::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListProducts::class)
            ->assertSuccessful();

        expect(ProductResource::getEloquentQuery()->count())->toBe(3);
    });

    it('step 7.2 — tenant admin can create a product', function (): void {
        $tenant = Tenant::factory()->create();
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'New Widget',
                'slug' => 'new-widget',
                'sku' => 'NW-001',
                'price_cents' => 9999,
                'category_id' => $category->id,
                'is_active' => true,
                'is_featured' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Product::class, ['sku' => 'NW-001']);
    });

    it('step 7.3 — tenant admin can list categories', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        Category::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListCategories::class)
            ->assertSuccessful();
    });

    it('step 7.4 — tenant admin can create a category', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Electronics',
                'slug' => 'electronics',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Category::class, ['slug' => 'electronics']);
    });
});

// ---------------------------------------------------------------------------
// 8. INVENTORY MANAGEMENT
// ---------------------------------------------------------------------------

describe('8. Admin — Inventory Management', function (): void {
    it('step 8.1 — tenant admin can list inventory in their tenant', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        Stock::factory()->count(2)->create([
            'product_id' => $product->id,
            'tenant_id' => $tenant->id,
        ]);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListInventory::class)
            ->assertSuccessful();
    });

    it('step 8.2 — InventoryResource is read-only', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $stock = Stock::factory()->create(['product_id' => $product->id, 'tenant_id' => $tenant->id]);
        $this->actingAs($admin);

        expect(InventoryResource::canCreate())->toBeFalse()
            ->and(InventoryResource::canDelete($stock))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// 9. PROMOTION MANAGEMENT
// ---------------------------------------------------------------------------

describe('9. Admin — Promotion Management', function (): void {
    it('step 9.1 — tenant admin can list promotions in their tenant', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        Promotion::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListPromotions::class)
            ->assertSuccessful();
    });

    it('step 9.2 — tenant admin can create a promotion', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Spring Sale',
                'code' => 'SPRING20',
                'discount_type' => 'fixed',
                'discount_value' => 2000,
                'scope' => 'all',
                'starts_at' => now()->toDateTimeLocalString(),
                'ends_at' => now()->addMonth()->toDateTimeLocalString(),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(Promotion::class, ['code' => 'SPRING20']);
    });

    it('step 9.3 — promotion creation fails without required fields', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(CreatePromotion::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'discount_type', 'scope', 'starts_at', 'ends_at']);
    });

    it('step 9.4 — super admin can resolve any promotion regardless of tenant', function (): void {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Context::add('tenant_id', $tenantA->id);

        $promotion = Promotion::withoutTenancy()->create([
            'name' => 'Cross-Tenant Promo',
            'tenant_id' => $tenantB->id,
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'scope' => 'all',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        $resolved = PromotionResource::resolveRecordRouteBinding($promotion->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($promotion->id);
    });
});

// ---------------------------------------------------------------------------
// 10. REVIEW MODERATION
// ---------------------------------------------------------------------------

describe('10. Super Admin — Review Moderation', function (): void {
    it('step 10.1 — super admin can access the review resource', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(ReviewResource::canAccess())->toBeTrue();
    });

    it('step 10.2 — reviews cannot be created via control plane', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(ReviewResource::canCreate())->toBeFalse();
    });

    it('step 10.3 — super admin can list reviews', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        // Each review must be from a unique user (unique constraint: tenant_id + product_id + user_id)
        $users = User::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        foreach ($users as $user) {
            Review::factory()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
            ]);
        }
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListReviews::class)
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 11. CONVERSATION (CHAT) MANAGEMENT
// ---------------------------------------------------------------------------

describe('11. Super Admin — Chat & Support Management', function (): void {
    it('step 11.1 — super admin can access conversation resource', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(ConversationResource::canAccess())->toBeTrue();
    });

    it('step 11.2 — conversations cannot be created via control plane', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(ConversationResource::canCreate())->toBeFalse();
    });

    it('step 11.3 — super admin sees only platform support conversations', function (): void {
        $tenant = Tenant::factory()->create();
        $customer = User::factory()->create(['tenant_id' => $tenant->id]);

        // Store conversation (belongs to tenant)
        Conversation::query()->withoutTenancy()->create([
            'user_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'type' => 'store',
            'status' => 'open',
        ]);

        // Support conversation (platform-level)
        Conversation::query()->withoutTenancy()->create([
            'user_id' => $customer->id,
            'tenant_id' => null,
            'type' => 'support',
            'status' => 'open',
        ]);

        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        $query = ConversationResource::getEloquentQuery();
        expect($query->count())->toBe(1)
            ->and($query->first()->type->value)->toBe('support');
    });

    it('step 11.4 — super admin can list support conversations', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListConversations::class)
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 12. LOYALTY & REFERRAL OVERSIGHT
// ---------------------------------------------------------------------------

describe('12. Super Admin — Loyalty & Referral Oversight', function (): void {
    it('step 12.1 — super admin can access loyalty accounts resource', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(LoyaltyAccountResource::canAccess())->toBeTrue();
    });

    it('step 12.2 — super admin can list loyalty accounts', function (): void {
        $tenant = Tenant::factory()->create();
        $users = User::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        foreach ($users as $user) {
            LoyaltyAccount::factory()->withPoints(100)->create(['user_id' => $user->id]);
        }
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListLoyaltyAccounts::class)
            ->assertSuccessful();
    });

    it('step 12.3 — super admin can access referral codes resource', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(ReferralCodeResource::canAccess())->toBeTrue();
    });

    it('step 12.4 — super admin can list referral codes', function (): void {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        ReferralCode::factory()->count(2)->create(['user_id' => $user->id]);
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListReferralCodes::class)
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 13. GIFT CARD MANAGEMENT
// ---------------------------------------------------------------------------

describe('13. Super Admin — Gift Card Management', function (): void {
    it('step 13.1 — super admin can access gift cards resource', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(GiftCardResource::canAccess())->toBeTrue();
    });

    it('step 13.2 — super admin can list gift cards', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        GiftCard::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(ListGiftCards::class)
            ->assertSuccessful();
    });

    it('step 13.3 — super admin can create a gift card', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        Livewire::test(CreateGiftCard::class)
            ->fillForm([
                'initial_balance_cents' => 5000,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(GiftCard::class, [
            'initial_balance_cents' => 5000,
        ]);
    });
});

// ---------------------------------------------------------------------------
// 14. SYSTEM CONFIGURATION
// ---------------------------------------------------------------------------

describe('14. Admin — System Configuration', function (): void {
    it('step 14.1 — tenant admin can access feature flags resource', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        expect(FeatureFlagResource::canAccess())->toBeTrue();
    });

    it('step 14.2 — tenant admin can list feature flags', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListFeatureFlags::class)
            ->assertSuccessful();
    });

    it('step 14.3 — tenant admin can access roles resource', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        expect(RoleResource::canAccess())->toBeTrue();
    });

    it('step 14.4 — tenant admin can list roles', function (): void {
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);

        Livewire::test(ListRoles::class)
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// 15. DASHBOARDS & MONITORING PAGES
// ---------------------------------------------------------------------------

describe('15. Super Admin — Dashboards & Monitoring', function (): void {
    it('step 15.1 — super admin can access advanced analytics', function (): void {
        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        expect(AdvancedAnalytics::canAccess())->toBeTrue();
    });

    it('step 15.2 — super admin can access the fraud dashboard', function (): void {
        $superAdmin = createSuperAdmin();
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $this->actingAs($superAdmin);

        expect(FraudDashboard::canAccess())->toBeTrue();
    });

    it('step 15.3 — super admin can access operations dashboard', function (): void {
        $superAdmin = createSuperAdmin();
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $this->actingAs($superAdmin);

        expect(OperationsDashboard::canAccess())->toBeTrue();
    });

    it('step 15.4 — super admin can view the audit log', function (): void {
        $superAdmin = createSuperAdmin();
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $this->actingAs($superAdmin);

        Livewire::test(AuditLogPage::class)
            ->assertSuccessful();
    });

    it('step 15.5 — super admin can view anomalies page', function (): void {
        $superAdmin = createSuperAdmin();
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $this->actingAs($superAdmin);

        Livewire::test(Anomalies::class)
            ->assertSuccessful();
    });

    it('step 15.6 — system health page is restricted to tenant admins (not super admins)', function (): void {
        $superAdmin = createSuperAdmin();
        $tenant = Tenant::factory()->create();
        Context::add('tenant_id', $tenant->id);
        $this->actingAs($superAdmin);

        expect(SystemHealth::canAccess())->toBeFalse();

        // Tenant admin can access it
        $admin = createAdminForTenant($tenant);
        $this->actingAs($admin);
        expect(SystemHealth::canAccess())->toBeTrue();
    });

    it('step 15.7 — customers are denied access to all admin pages', function (): void {
        $tenant = Tenant::factory()->create();
        $customerRole = Role::where('name', 'customer')->first();
        $customer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $customerRole->id,
        ]);
        $this->actingAs($customer);

        expect(SuperAdminDashboard::canAccess())->toBeFalse()
            ->and(TenantResource::canAccess())->toBeFalse()
            ->and(FraudDashboard::canAccess())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// 16. CROSS-TENANT DATA ISOLATION
// ---------------------------------------------------------------------------

describe('16. Super Admin — Cross-Tenant Data Visibility', function (): void {
    it('step 16.1 — super admin with no tenant selected sees all records', function (): void {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Product::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Product::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        // No tenant context set — super admin sees all
        $count = ProductResource::getEloquentQuery()->count();
        expect($count)->toBe(5);
    });

    it('step 16.2 — super admin with a tenant selected sees only that tenant records', function (): void {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Product::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Product::factory()->count(3)->create(['tenant_id' => $tenantB->id]);
        Context::add('tenant_id', $tenantA->id);

        $superAdmin = createSuperAdmin();
        $this->actingAs($superAdmin);

        $count = ProductResource::getEloquentQuery()->count();
        expect($count)->toBe(2);
    });
});
