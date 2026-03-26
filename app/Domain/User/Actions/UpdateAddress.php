<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\DTOs\UpdateAddressData;
use App\Domain\User\Models\Address;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAddress
{
    public function execute(UpdateAddressData $data): Address
    {
        return DB::transaction(function () use ($data): Address {
            $address = Address::query()
                ->where('id', $data->addressId)
                ->where('user_id', $data->userId)
                ->firstOrFail();

            if ($data->isDefault) {
                Address::query()
                    ->where('user_id', $data->userId)
                    ->where('id', '!=', $data->addressId)
                    ->update(['is_default' => false]);
            }

            $address->update([
                'name' => $data->name,
                'phone' => $data->phone,
                'address_line_1' => $data->addressLine1,
                'address_line_2' => $data->addressLine2,
                'city' => $data->city,
                'state' => $data->state,
                'postal_code' => $data->postalCode,
                'country' => $data->country,
                'is_default' => $data->isDefault,
            ]);

            return $address->fresh();
        });
    }
}
