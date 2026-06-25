<?php

namespace App\Jobs;

use App\Enums\StorefrontPlatform;
use App\Models\StorefrontConnection;
use App\Services\ExternalOrderImportService;
use App\Services\ExternalOrders\ShopifyOrderTransformer;
use App\Services\ExternalOrders\SquarespaceOrderTransformer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessExternalOrderWebhook implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $connectionId,
        public string $platform,
        public array $payload,
        public ?string $deliveryId = null,
    ) {}

    public function handle(
        ExternalOrderImportService $imports,
        ShopifyOrderTransformer $shopify,
        SquarespaceOrderTransformer $squarespace,
    ): void {
        $connection = StorefrontConnection::query()->find($this->connectionId);

        if (! $connection || $connection->platform->value !== $this->platform) {
            return;
        }

        $normalized = match ($connection->platform) {
            StorefrontPlatform::Shopify => $shopify->transform($this->payload),
            StorefrontPlatform::Squarespace => $squarespace->transform($this->payload),
            default => null,
        };

        if (! $normalized) {
            return;
        }

        $imports->import($connection, $normalized, $this->payload, $this->deliveryId);
    }
}
