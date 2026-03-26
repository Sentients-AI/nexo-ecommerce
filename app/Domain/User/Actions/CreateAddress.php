<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\DTOs\CreateAddressData;
use App\Domain\User\Models\Address;
use Illuminate\Support\Facades\DB;

final readonly class CreateAddress
{
    public function execute(CreateAddressData $data): Address
    {
        return DB::transaction(function () use ($data): Address {
            if ($data->isDefault) {
                Address::query()
                    ->where('user_id', $data->userId)
                    ->update(['is_default' => false]);
            }

            return Address::query()->create([
                'user_id' => $data->userId,
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
        });
    }
}
