<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\User\Actions\CreateAddress;
use App\Domain\User\Actions\DeleteAddress;
use App\Domain\User\Actions\SetDefaultAddress;
use App\Domain\User\Actions\UpdateAddress;
use App\Domain\User\DTOs\CreateAddressData;
use App\Domain\User\DTOs\UpdateAddressData;
use App\Domain\User\Models\Address;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAddressRequest;
use App\Http\Requests\Api\V1\UpdateAddressRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AddressController extends Controller
{
    public function __construct(
        private readonly CreateAddress $createAddress,
        private readonly UpdateAddress $updateAddress,
        private readonly DeleteAddress $deleteAddress,
        private readonly SetDefaultAddress $setDefaultAddress,
    ) {}

    public function index(Request $request): Response
    {
        $addresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Address $a): array => $this->formatAddress($a))
            ->toArray();

        return Inertia::render('Profile/Addresses', [
            'addresses' => $addresses,
        ]);
    }

    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $this->createAddress->execute(
            CreateAddressData::fromArray($request->validated(), $request->user()->id)
        );

        return redirect()->route('addresses.index', ['locale' => app()->getLocale()])
            ->with('success', 'Address added successfully.');
    }

    public function update(UpdateAddressRequest $request): RedirectResponse
    {
        $address = $this->findAddressOrFail($request);

        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->updateAddress->execute(
            UpdateAddressData::fromArray($request->validated(), $address->id, $request->user()->id)
        );

        return redirect()->route('addresses.index', ['locale' => app()->getLocale()])
            ->with('success', 'Address updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $address = $this->findAddressOrFail($request);

        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->deleteAddress->execute($address->id, $request->user()->id);

        return redirect()->route('addresses.index', ['locale' => app()->getLocale()])
            ->with('success', 'Address deleted.');
    }

    public function setDefault(Request $request): RedirectResponse
    {
        $address = $this->findAddressOrFail($request);

        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->setDefaultAddress->execute($address->id, $request->user()->id);

        return redirect()->route('addresses.index', ['locale' => app()->getLocale()])
            ->with('success', 'Default address updated.');
    }

    private function findAddressOrFail(Request $request): Address
    {
        return Address::query()->findOrFail((int) $request->route('address'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'name' => $address->name,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'is_default' => $address->is_default,
        ];
    }
}
