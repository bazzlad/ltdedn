<?php

namespace App\DTOs\ExternalOrders;

final readonly class NormalizedAddressData
{
    public function __construct(
        public ?string $name,
        public ?string $phone,
        public ?string $line1,
        public ?string $line2,
        public ?string $city,
        public ?string $state,
        public ?string $postalCode,
        public ?string $country,
    ) {}
}
