<?php

namespace App\DTOs\ExternalOrders;

final readonly class NormalizedOrderData
{
    /**
     * @param  array<int, NormalizedLineItemData>  $lineItems
     */
    public function __construct(
        public string $externalOrderId,
        public ?string $externalOrderNumber,
        public ?string $customerEmail,
        public NormalizedAddressData $shippingAddress,
        public string $currency,
        public int $subtotalAmount,
        public int $shippingAmount,
        public int $taxAmount,
        public int $totalAmount,
        public string $paymentStatus,
        public ?string $fulfilmentStatus,
        public array $lineItems,
        public array $meta = [],
    ) {}

    public function isPaid(): bool
    {
        return in_array(strtolower($this->paymentStatus), ['approved', 'paid', 'paid_ready', 'captured', 'complete', 'completed'], true);
    }
}
