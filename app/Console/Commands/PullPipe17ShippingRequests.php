<?php

namespace App\Console\Commands;

use App\DTOs\ExternalOrders\NormalizedAddressData;
use App\DTOs\ExternalOrders\NormalizedLineItemData;
use App\DTOs\ExternalOrders\NormalizedOrderData;
use App\Enums\ExternalImportStatus;
use App\Enums\StorefrontPlatform;
use App\Models\ExternalOrderImport;
use App\Models\StorefrontConnection;
use App\Services\ExternalOrderImportService;
use App\Services\Pipe17\Pipe17Client;
use Illuminate\Console\Command;

class PullPipe17ShippingRequests extends Command
{
    protected $signature = 'pipe17:pull-shipping-requests
        {connection? : Optional storefront connection ID}
        {--since= : ISO timestamp to pass to Pipe17 updatedSince}
        {--status=* : Pipe17 shipping request status filter}';

    protected $description = 'Poll Pipe17 shipping requests assigned to LTD EDN and import them for fulfillment.';

    public function handle(Pipe17Client $client, ExternalOrderImportService $imports): int
    {
        $connections = $this->connections();
        $imported = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            $since = $this->option('since') ?: data_get($connection->last_sync_meta, 'pipe17_last_updated_since');
            $statuses = $this->option('status') ?: [];
            $connectionFailed = false;

            try {
                $requests = $client->listShippingRequests($connection, $since ? (string) $since : null, $statuses);
            } catch (\Throwable $throwable) {
                $failed++;
                $this->error("Pipe17 pull failed for connection {$connection->id}: {$throwable->getMessage()}");

                continue;
            }

            foreach ($requests as $request) {
                $normalized = $this->normalize($request);

                if (blank($normalized->externalOrderId) || $normalized->lineItems === []) {
                    $failed++;
                    $connectionFailed = true;
                    $this->warn('Skipping malformed Pipe17 shipping request without an ID or line items.');

                    continue;
                }

                $import = $imports->import($connection, $normalized, $request, $this->deliveryId($request));

                if ($import->status === ExternalImportStatus::Failed) {
                    $failed++;
                    $connectionFailed = true;
                    $this->warn("Pipe17 request {$normalized->externalOrderId} failed to import.");

                    continue;
                }

                if ($this->shouldAcknowledge($import) && $import->order_id) {
                    try {
                        $client->markShippingRequestSent($connection, $normalized->externalOrderId, $import->order_id);
                    } catch (\Throwable $throwable) {
                        $failed++;
                        $connectionFailed = true;
                        $this->warn("Imported Pipe17 request {$normalized->externalOrderId}, but status update failed: {$throwable->getMessage()}");
                    }
                }

                $imported++;
            }

            $lastSyncMeta = array_merge($connection->last_sync_meta ?? [], [
                'pipe17_last_pull_count' => count($requests),
            ]);

            if (! $connectionFailed) {
                $lastSyncMeta['pipe17_last_updated_since'] = now()->toIso8601String();
            }

            $connection->update([
                'last_synced_at' => now(),
                'last_sync_meta' => $lastSyncMeta,
            ]);
        }

        $this->info("Pipe17 pull complete: {$imported} request(s) handled, {$failed} failure(s).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, StorefrontConnection>
     */
    private function connections()
    {
        return StorefrontConnection::query()
            ->where('platform', StorefrontPlatform::Pipe17->value)
            ->where('status', 'active')
            ->when($this->argument('connection'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->limit(1)
            ->get();
    }

    private function shouldAcknowledge(ExternalOrderImport $import): bool
    {
        return $import->status === ExternalImportStatus::Processed
            || (
                $import->status === ExternalImportStatus::Ignored
                && $import->order_id
                && str_contains((string) $import->error_details, 'Duplicate external order id')
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalize(array $payload): NormalizedOrderData
    {
        $shipping = $this->shippingAddress($payload);
        $lineItems = collect(data_get($payload, 'lineItems', data_get($payload, 'items', data_get($payload, 'orderItems', []))))
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                $quantity = max(1, (int) data_get($item, 'quantity', data_get($item, 'qty', 1)));
                $unitAmount = $this->minorAmount(data_get($item, 'unitPrice', data_get($item, 'price', data_get($item, 'unit_amount', 0))));

                return new NormalizedLineItemData(
                    skuCode: trim((string) data_get($item, 'sku', data_get($item, 'code', data_get($item, 'product.sku', '')))),
                    productTitle: (string) data_get($item, 'name', data_get($item, 'title', data_get($item, 'product.name', 'External item'))),
                    variantTitle: data_get($item, 'variantTitle', data_get($item, 'variant_title')),
                    quantity: $quantity,
                    unitAmount: $unitAmount,
                    lineTotal: $this->minorAmount(data_get($item, 'lineTotal', data_get($item, 'total', null))) ?: $unitAmount * $quantity,
                    attributes: [
                        'pipe17_line_item_id' => data_get($item, 'id'),
                        'pipe17_order_line_item_id' => data_get($item, 'orderLineItemId'),
                    ],
                );
            })
            ->values()
            ->all();

        $status = (string) data_get($payload, 'status', 'readyForFulfillment');

        return new NormalizedOrderData(
            externalOrderId: $this->shippingRequestId($payload),
            externalOrderNumber: $this->orderNumber($payload),
            customerEmail: data_get($payload, 'customer.email', data_get($payload, 'email')),
            shippingAddress: $shipping,
            currency: (string) data_get($payload, 'currency', data_get($payload, 'order.currency', 'GBP')),
            subtotalAmount: $this->minorAmount(data_get($payload, 'subTotalPrice', data_get($payload, 'subtotal', data_get($payload, 'order.subTotalPrice', 0)))),
            shippingAmount: $this->minorAmount(data_get($payload, 'shippingPrice', data_get($payload, 'shippingTotal', 0))),
            taxAmount: $this->minorAmount(data_get($payload, 'orderTax', data_get($payload, 'taxTotal', 0))),
            totalAmount: $this->minorAmount(data_get($payload, 'totalPrice', data_get($payload, 'total', data_get($payload, 'order.totalPrice', 0)))),
            paymentStatus: $this->paymentStatus($payload, $status),
            fulfilmentStatus: $status,
            lineItems: $lineItems,
            meta: [
                'pipe17_order_id' => data_get($payload, 'orderId', data_get($payload, 'order.id')),
                'pipe17_ext_order_id' => data_get($payload, 'extOrderId', data_get($payload, 'order.extOrderId')),
                'pipe17_order_source' => data_get($payload, 'orderSource', data_get($payload, 'order.orderSource')),
                'pipe17_shipping_request_status' => $status,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shippingAddress(array $payload): NormalizedAddressData
    {
        $shipping = data_get($payload, 'shippingAddress', data_get($payload, 'shipTo', []));
        $shipping = is_array($shipping) ? $shipping : [];

        $firstName = (string) data_get($shipping, 'firstName', '');
        $lastName = (string) data_get($shipping, 'lastName', '');

        return new NormalizedAddressData(
            name: data_get($shipping, 'name') ?: trim($firstName.' '.$lastName) ?: data_get($shipping, 'company'),
            phone: data_get($shipping, 'phone'),
            line1: data_get($shipping, 'address1', data_get($shipping, 'line1')),
            line2: data_get($shipping, 'address2', data_get($shipping, 'line2')),
            city: data_get($shipping, 'city'),
            state: data_get($shipping, 'stateOrProvince', data_get($shipping, 'state')),
            postalCode: data_get($shipping, 'zipCodeOrPostalCode', data_get($shipping, 'postalCode', data_get($shipping, 'zip'))),
            country: data_get($shipping, 'country'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shippingRequestId(array $payload): string
    {
        return (string) data_get($payload, 'id', data_get($payload, 'shippingRequestId', data_get($payload, 'requestId', '')));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function orderNumber(array $payload): ?string
    {
        $value = data_get($payload, 'orderNumber', data_get($payload, 'order.orderNumber', data_get($payload, 'extOrderId', data_get($payload, 'order.extOrderId'))));

        return filled($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliveryId(array $payload): ?string
    {
        $value = data_get($payload, 'updatedAt', data_get($payload, 'updated_at'));

        return $value ? $this->shippingRequestId($payload).':'.$value : $this->shippingRequestId($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentStatus(array $payload, string $status): string
    {
        $paymentStatus = data_get($payload, 'paymentStatus', data_get($payload, 'order.paymentStatus'));

        if (filled($paymentStatus)) {
            return (string) $paymentStatus;
        }

        return in_array($status, ['canceled', 'cancelled', 'failed', 'onHold'], true) ? 'pending' : 'paid_ready';
    }

    private function minorAmount(mixed $amount): int
    {
        if (is_array($amount)) {
            $amount = $amount['value'] ?? $amount['amount'] ?? 0;
        }

        return (int) round(((float) $amount) * 100);
    }
}
