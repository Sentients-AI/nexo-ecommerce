<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Role\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    /**
     * All roles in the system.
     *
     * @var array<string, string>
     */
    private array $roles = [
        'super_admin' => 'Super Administrator with access to all tenants',
        'admin' => 'Tenant Administrator with full access to tenant resources',
        'manager' => 'Manager with access to operations and reporting',
        'support' => 'Support staff with read access and limited write access',
        'finance' => 'Finance team with access to payments and refunds',
        'warehouse' => 'Warehouse staff with inventory management access',
        'customer' => 'Regular customer account',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->roles as $name => $description) {
            Role::firstOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }
    }
}
