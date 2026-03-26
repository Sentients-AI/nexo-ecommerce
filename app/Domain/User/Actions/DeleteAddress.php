<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Models\Address;
use Illuminate\Support\Facades\DB;

final readonly class DeleteAddress
{
    public function execute(int $addressId, int $userId): void
    {
        DB::transaction(function () use ($addressId, $userId): void {
            $address = Address::query()
                ->where('id', $addressId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $wasDefault = $address->is_default;

            $address->delete();

            if ($wasDefault) {
                Address::query()
                    ->where('user_id', $userId)
                    ->latest()
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }
}
