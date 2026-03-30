<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Actions;

use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateTenantWithAdminUser
{
    /**
     * @param  array<string, string>  $settings
     * @return array{tenant: Tenant, user: User}
     */
    public function execute(
        string $storeName,
        string $storeSlug,
        string $storeEmail,
        string $userName,
        string $userEmail,
        string $password,
        array $settings = [],
    ): array {
        return DB::transaction(function () use (
            $storeName, $storeSlug, $storeEmail, $userName, $userEmail, $password, $settings
        ): array {
            $trialDays = (int) config('tenancy.trial_days', 14);

            $tenant = Tenant::create([
                'name' => $storeName,
                'slug' => $storeSlug,
                'email' => $storeEmail,
                'is_active' => true,
                'trial_ends_at' => $trialDays > 0 ? Carbon::now()->addDays($trialDays) : null,
                'settings' => array_merge(config('tenancy.default_settings', []), $settings),
            ]);

            $adminRole = Role::where('name', 'admin')->first();

            $user = User::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $userName,
                'email' => $userEmail,
                'password' => Hash::make($password),
                'role_id' => $adminRole?->id,
                'email_verified_at' => now(),
            ]);

            return compact('tenant', 'user');
        });
    }
}
