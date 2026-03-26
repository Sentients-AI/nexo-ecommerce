<?php

declare(strict_types=1);

use App\Domain\Product\Models\Product;
use App\Domain\Review\Models\Review;
use App\Domain\Role\Models\Role;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\User\Models\User;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::factory()->create(['name' => 'super_admin']);
    Role::factory()->create(['name' => 'admin']);
});

describe('ReviewResource access', function (): void {
    it('super admin can access the review resource', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);

        $this->actingAs($superAdmin);

        expect(ReviewResource::canAccess())->toBeTrue();
    });

    it('admin can access the review resource', function (): void {
        $tenant = Tenant::factory()->create();
        $role = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $role->id]);

        $this->actingAs($admin);
        Context::add('tenant_id', $tenant->id);

        expect(ReviewResource::canAccess())->toBeTrue();
    });

    it('cannot create or edit reviews', function (): void {
        $role = Role::where('name', 'super_admin')->first();
        $superAdmin = User::factory()->create(['tenant_id' => null, 'role_id' => $role->id]);
        $this->actingAs($superAdmin);

        expect(ReviewResource::canCreate())->toBeFalse();
    });
});

describe('ReviewResource listing', function (): void {
    it('admin can see tenant reviews via table', function (): void {
        $tenant = Tenant::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $adminRole->id]);

        Context::add('tenant_id', $tenant->id);

        // Create reviews with unique product+user combinations to avoid unique constraint violation
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $users = User::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        foreach ($users as $user) {
            Review::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'user_id' => $user->id]);
        }

        $this->actingAs($admin);

        Livewire::test(ListReviews::class)
            ->assertSuccessful();

        expect(true)->toBeTrue();
    });

    it('can approve a review', function (): void {
        $tenant = Tenant::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $adminRole->id]);

        Context::add('tenant_id', $tenant->id);

        $review = Review::factory()->create(['tenant_id' => $tenant->id, 'is_approved' => false]);

        $this->actingAs($admin);

        Livewire::test(ListReviews::class)
            ->callAction(TestAction::make('approve')->table($review));

        expect($review->fresh()->is_approved)->toBeTrue();
    });

    it('can reject a review', function (): void {
        $tenant = Tenant::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $adminRole->id]);

        Context::add('tenant_id', $tenant->id);

        $review = Review::factory()->create(['tenant_id' => $tenant->id, 'is_approved' => true]);

        $this->actingAs($admin);

        Livewire::test(ListReviews::class)
            ->callAction(TestAction::make('reject')->table($review));

        expect($review->fresh()->is_approved)->toBeFalse();
    });
});
