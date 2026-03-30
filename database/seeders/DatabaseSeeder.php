<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. First seed roles and tenants
        $this->call([
            RoleSeeder::class,
            TenantSeeder::class,
            ShippingMethodSeeder::class,
        ]);

        // 2. Create hardcoded users
        $this->createHardcodedUsers();

        // 3. Seed other data with tenant context
        $this->seedTenantData();
    }

    /**
     * Create hardcoded users with specific roles and tenants.
     */
    private function createHardcodedUsers(): void
    {
        // Get roles
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $supportRole = Role::where('name', 'support')->first();
        $financeRole = Role::where('name', 'finance')->first();
        $warehouseRole = Role::where('name', 'warehouse')->first();
        $customerRole = Role::where('name', 'customer')->first();

        // Get tenants
        $acmeStore = Tenant::where('slug', 'acme-store')->first();
        $gadgetWorld = Tenant::where('slug', 'gadget-world')->first();
        $fashionHub = Tenant::where('slug', 'fashion-hub')->first();

        // =====================
        // SUPER ADMINS (no tenant - platform-level access)
        // =====================
        User::firstOrCreate(
            ['email' => 'superadmin@platform.com'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
                'tenant_id' => null,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'john.doe@platform.com'],
            [
                'name' => 'John Doe',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
                'tenant_id' => null,
                'email_verified_at' => now(),
            ]
        );

        // =====================
        // ACME STORE USERS
        // =====================
        User::firstOrCreate(
            ['email' => 'admin@acme-store.com'],
            [
                'name' => 'ACME Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'tenant_id' => $acmeStore->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@acme-store.com'],
            [
                'name' => 'ACME Manager',
                'password' => Hash::make('password'),
                'role_id' => $managerRole->id,
                'tenant_id' => $acmeStore->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'support@acme-store.com'],
            [
                'name' => 'ACME Support',
                'password' => Hash::make('password'),
                'role_id' => $supportRole->id,
                'tenant_id' => $acmeStore->id,
                'email_verified_at' => now(),
            ]

        );

        User::firstOrCreate(
            ['email' => 'finance@acme-store.com'],
            [
                'name' => 'ACME Finance',
                'password' => Hash::make('password'),
                'role_id' => $financeRole->id,
                'tenant_id' => $acmeStore->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'warehouse@acme-store.com'],
            [
                'name' => 'ACME Warehouse',
                'password' => Hash::make('password'),
                'role_id' => $warehouseRole->id,
                'tenant_id' => $acmeStore->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer1@acme-store.com'],
            [
                'name' => 'Alice Customer',
                'password' => Hash::make('password'),
                'role_id' => $customerRole->id,
                'tenant_id' => $acmeStore->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer2@acme-store.com'],
            [
                'name' => 'Bob Customer',
                'password' => Hash::make('password'),
                'role_id' => $customerRole->id,
                'tenant_id' => $acmeStore->id,
                'email_verified_at' => now(),
            ]
        );

        // =====================
        // GADGET WORLD USERS
        // =====================
        User::firstOrCreate(
            ['email' => 'admin@gadget-world.com'],
            [
                'name' => 'Gadget World Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'tenant_id' => $gadgetWorld->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@gadget-world.com'],
            [
                'name' => 'Gadget World Manager',
                'password' => Hash::make('password'),
                'role_id' => $managerRole->id,
                'tenant_id' => $gadgetWorld->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer@gadget-world.com'],
            [
                'name' => 'Charlie Customer',
                'password' => Hash::make('password'),
                'role_id' => $customerRole->id,
                'tenant_id' => $gadgetWorld->id,
                'email_verified_at' => now(),
            ]
        );

        // =====================
        // FASHION HUB USERS
        // =====================
        User::firstOrCreate(
            ['email' => 'admin@fashionhub.com'],
            [
                'name' => 'Fashion Hub Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'tenant_id' => $fashionHub->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'support@fashionhub.com'],
            [
                'name' => 'Fashion Hub Support',
                'password' => Hash::make('password'),
                'role_id' => $supportRole->id,
                'tenant_id' => $fashionHub->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer@fashionhub.com'],
            [
                'name' => 'Diana Customer',
                'password' => Hash::make('password'),
                'role_id' => $customerRole->id,
                'tenant_id' => $fashionHub->id,
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * Seed tenant-specific data.
     */
    private function seedTenantData(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            // Set tenant context for seeding
            Context::add('tenant_id', $tenant->id);

            $this->call([
                CategorySeeder::class,
                ProductSeeder::class,
                StockSeeder::class,
                PromotionSeeder::class,
            ]);

            Context::forget('tenant_id');
        }
    }
}
