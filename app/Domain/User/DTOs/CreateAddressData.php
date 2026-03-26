<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

final readonly class CreateAddressData
{
    public function __construct(
        public int $userId,
        public string $name,
        public ?string $phone,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public ?string $state,
        public string $postalCode,
        public string $country,
        public bool $isDefault,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            name: $data['name'],
            phone: $data['phone'] ?? null,
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'],
            state: $data['state'] ?? null,
            postalCode: $data['postal_code'],
            country: $data['country'],
            isDefault: (bool) ($data['is_default'] ?? false),
        );
    }
}
