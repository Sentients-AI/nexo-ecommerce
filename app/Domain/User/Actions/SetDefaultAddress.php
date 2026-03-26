<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Models\Address;
use Illuminate\Support\Facades\DB;

final readonly class SetDefaultAddress
{
    public function execute(int $addressId, int $userId): Address
    {
        return DB::transaction(function () use ($addressId, $userId): Address {
            $address = Address::query()
                ->where('id', $addressId)
                ->where('user_id', $userId)
                ->firstOrFail();

            Address::query()
                ->where('user_id', $userId)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }
}
