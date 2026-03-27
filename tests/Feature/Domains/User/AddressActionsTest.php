<?php

declare(strict_types=1);

use App\Domain\User\Actions\CreateAddress;
use App\Domain\User\Actions\DeleteAddress;
use App\Domain\User\Actions\SetDefaultAddress;
use App\Domain\User\Actions\UpdateAddress;
use App\Domain\User\DTOs\CreateAddressData;
use App\Domain\User\DTOs\UpdateAddressData;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeAddressData(int $userId, bool $isDefault = false): CreateAddressData
{
    return new CreateAddressData(
        userId: $userId,
        name: 'Home',
        phone: '+60123456789',
        addressLine1: '123 Main St',
        addressLine2: null,
        city: 'Kuala Lumpur',
        state: 'WP',
        postalCode: '50000',
        country: 'MY',
        isDefault: $isDefault,
    );
}

describe('CreateAddress action', function () {
    it('creates an address for a user', function () {
        $user = User::factory()->create();
        $action = app(CreateAddress::class);

        $address = $action->execute(makeAddressData($user->id));

        expect($address->id)->toBeInt()
            ->and($address->user_id)->toBe($user->id)
            ->and($address->city)->toBe('Kuala Lumpur')
            ->and($address->is_default)->toBeFalse();
    });

    it('sets new address as default and unsets previous default', function () {
        $user = User::factory()->create();
        $action = app(CreateAddress::class);

        $first = $action->execute(makeAddressData($user->id, isDefault: true));
        expect($first->is_default)->toBeTrue();

        $second = $action->execute(makeAddressData($user->id, isDefault: true));

        expect($second->is_default)->toBeTrue()
            ->and($first->fresh()->is_default)->toBeFalse();
    });

    it('creates non-default address without touching existing default', function () {
        $user = User::factory()->create();
        $action = app(CreateAddress::class);

        $default = $action->execute(makeAddressData($user->id, isDefault: true));
        $action->execute(makeAddressData($user->id, isDefault: false));

        expect($default->fresh()->is_default)->toBeTrue();
    });
});

describe('UpdateAddress action', function () {
    it('updates address fields', function () {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['city' => 'Old City', 'is_default' => false]);
        $action = app(UpdateAddress::class);

        $updated = $action->execute(new UpdateAddressData(
            addressId: $address->id,
            userId: $user->id,
            name: 'Work',
            phone: null,
            addressLine1: '456 New Ave',
            addressLine2: null,
            city: 'Petaling Jaya',
            state: 'Selangor',
            postalCode: '47500',
            country: 'MY',
            isDefault: false,
        ));

        expect($updated->city)->toBe('Petaling Jaya')
            ->and($updated->address_line_1)->toBe('456 New Ave');
    });

    it('promotes to default and demotes others on update', function () {
        $user = User::factory()->create();
        $existingDefault = Address::factory()->for($user)->create(['is_default' => true]);
        $other = Address::factory()->for($user)->create(['is_default' => false]);
        $action = app(UpdateAddress::class);

        $action->execute(new UpdateAddressData(
            addressId: $other->id,
            userId: $user->id,
            name: 'Home',
            phone: null,
            addressLine1: '1 St',
            addressLine2: null,
            city: 'KL',
            state: null,
            postalCode: '50000',
            country: 'MY',
            isDefault: true,
        ));

        expect($other->fresh()->is_default)->toBeTrue()
            ->and($existingDefault->fresh()->is_default)->toBeFalse();
    });
});

describe('DeleteAddress action', function () {
    it('deletes an address', function () {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $action = app(DeleteAddress::class);

        $action->execute($address->id, $user->id);

        expect(Address::find($address->id))->toBeNull();
    });

    it('promotes next address to default when default is deleted', function () {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->create(['is_default' => true]);
        $other = Address::factory()->for($user)->create(['is_default' => false]);
        $action = app(DeleteAddress::class);

        $action->execute($default->id, $user->id);

        expect($other->fresh()->is_default)->toBeTrue();
    });

    it('throws when address does not belong to user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->for($otherUser)->create();
        $action = app(DeleteAddress::class);

        expect(fn () => $action->execute($address->id, $user->id))
            ->toThrow(ModelNotFoundException::class);
    });
});

describe('SetDefaultAddress action', function () {
    it('sets address as default and clears others', function () {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);
        $second = Address::factory()->for($user)->create(['is_default' => false]);
        $action = app(SetDefaultAddress::class);

        $result = $action->execute($second->id, $user->id);

        expect($result->is_default)->toBeTrue()
            ->and($first->fresh()->is_default)->toBeFalse()
            ->and($second->fresh()->is_default)->toBeTrue();
    });
});
