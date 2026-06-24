<?php

namespace App\DTOs\ExternalOrders;

final readonly class NormalizedLineItemData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $skuCode,
        public string $productTitle,
        public ?string $variantTitle,
        public int $quantity,
        public int $unitAmount,
        public int $lineTotal,
        public array $attributes = [],
    ) {}
}
