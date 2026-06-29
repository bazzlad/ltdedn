<?php

namespace App\Services;

use App\DTOs\ExternalOrders\NormalizedLineItemData;
use App\DTOs\ExternalOrders\NormalizedOrderData;
use App\Enums\ExternalImportStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Models\ExternalOrderImport;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\StorefrontConnection;
use App\Models\User;
use App\Notifications\ExternalOrderExceptionNotification;
use App\Services\StorefrontConnect\StorefrontConnectionStatusService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ExternalOrderImportService
{
    public function __construct(
        private readonly StorefrontConnectionStatusService $connectionStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function import(StorefrontConnection $connection, NormalizedOrderData $normalized, array $rawPayload, ?string $deliveryId = null): ExternalOrderImport
    {
        $payloadHash = hash('sha256', json_encode($rawPayload, JSON_THROW_ON_ERROR));

        $existingImport = $this->findExistingImport($connection, $normalized, $payloadHash, $deliveryId);

        if ($existingImport) {
            return $existingImport;
        }

        try {
            return DB::transaction(function () use ($connection, $normalized, $rawPayload, $deliveryId, $payloadHash) {
                $existingOrder = Order::query()
                    ->where('storefront_connection_id', $connection->id)
                    ->where('source_platform', $connection->platform->value)
                    ->where('external_order_id', $normalized->externalOrderId)
                    ->first();

                $import = ExternalOrderImport::create([
                    'storefront_connection_id' => $connection->id,
                    'order_id' => $existingOrder?->id,
                    'platform' => $connection->platform,
                    'external_order_id' => $normalized->externalOrderId,
                    'delivery_id' => $deliveryId,
                    'payload_hash' => $payloadHash,
                    'raw_payload' => $rawPayload,
                    'status' => $existingOrder ? ExternalImportStatus::Ignored : ExternalImportStatus::Pending,
                    'error_details' => $existingOrder ? 'Duplicate external order id already imported.' : null,
                    'processed_at' => $existingOrder ? now() : null,
                ]);

                if ($existingOrder) {
                    return $import;
                }

                if (! $normalized->isPaid()) {
                    $import->update([
                        'status' => ExternalImportStatus::Ignored,
                        'error_details' => 'Order payment status is not paid.',
                        'processed_at' => now(),
                    ]);

                    return $import;
                }

                $allocation = $this->prepareAllocation($normalized);

                if ($allocation['error'] !== null) {
                    $order = $this->createExceptionOrder($connection, $normalized, $allocation['error']);
                    $import->update([
                        'order_id' => $order->id,
                        'status' => ExternalImportStatus::Exception,
                        'error_details' => $allocation['error'],
                        'processed_at' => now(),
                    ]);

                    DB::afterCommit(fn () => $this->notifyAdmins($order));

                    return $import;
                }

                $order = $this->createAllocatedOrder($connection, $normalized, $allocation['groups']);
                $import->update([
                    'order_id' => $order->id,
                    'status' => ExternalImportStatus::Processed,
                    'processed_at' => now(),
                ]);
                $this->connectionStatus->markSuccessfulTestOrder($connection);

                return $import;
            });
        } catch (QueryException $exception) {
            $existingImport = $this->findExistingImport($connection, $normalized, $payloadHash, $deliveryId);

            if ($existingImport) {
                return $existingImport;
            }

            return $this->recordFailedImport($connection, $normalized, $rawPayload, $deliveryId, $payloadHash, $exception->getMessage());
        } catch (Throwable $throwable) {
            return $this->recordFailedImport($connection, $normalized, $rawPayload, $deliveryId, $payloadHash, $throwable->getMessage());
        }
    }

    private function findExistingImport(StorefrontConnection $connection, NormalizedOrderData $normalized, string $payloadHash, ?string $deliveryId): ?ExternalOrderImport
    {
        return ExternalOrderImport::query()
            ->where('storefront_connection_id', $connection->id)
            ->where(function ($query) use ($normalized, $payloadHash, $deliveryId) {
                $query->where(function ($payloadQuery) use ($normalized, $payloadHash) {
                    $payloadQuery->where('external_order_id', $normalized->externalOrderId)
                        ->where('payload_hash', $payloadHash);
                });

                if ($deliveryId) {
                    $query->orWhere('delivery_id', $deliveryId);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function recordFailedImport(StorefrontConnection $connection, NormalizedOrderData $normalized, array $rawPayload, ?string $deliveryId, string $payloadHash, string $error): ExternalOrderImport
    {
        return ExternalOrderImport::firstOrCreate(
            [
                'storefront_connection_id' => $connection->id,
                'external_order_id' => $normalized->externalOrderId,
                'payload_hash' => $payloadHash,
            ],
            [
                'storefront_connection_id' => $connection->id,
                'platform' => $connection->platform,
                'external_order_id' => $normalized->externalOrderId,
                'delivery_id' => $deliveryId,
                'payload_hash' => $payloadHash,
                'raw_payload' => $rawPayload,
                'status' => ExternalImportStatus::Failed,
                'error_details' => $error,
                'processed_at' => now(),
            ],
        );
    }

    /**
     * @return array{error: ?string, groups: array<int, array{lines: array<int, NormalizedLineItemData>, sku: ProductSku, editions: \Illuminate\Support\Collection<int, ProductEdition>, quantity: int}>}
     */
    private function prepareAllocation(NormalizedOrderData $normalized): array
    {
        $groups = [];

        foreach ($normalized->lineItems as $lineItem) {
            $groups[$lineItem->skuCode]['lines'][] = $lineItem;
            $groups[$lineItem->skuCode]['quantity'] = ($groups[$lineItem->skuCode]['quantity'] ?? 0) + $lineItem->quantity;
        }

        $allocatedGroups = [];

        foreach ($groups as $skuCode => $group) {
            $sku = ProductSku::query()
                ->with('product')
                ->where('sku_code', $skuCode)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $sku) {
                return ['error' => "Unknown SKU {$skuCode}.", 'groups' => []];
            }

            $quantity = (int) $group['quantity'];

            if ($sku->stock_available < $quantity) {
                return ['error' => "Insufficient stock for SKU {$skuCode}.", 'groups' => []];
            }

            $editions = collect();
            if ($sku->product?->is_limited) {
                $editions = ProductEdition::query()
                    ->where('product_id', $sku->product_id)
                    ->where(function ($query) use ($sku) {
                        $query->whereNull('product_sku_id')
                            ->orWhere('product_sku_id', $sku->id);
                    })
                    ->where('status', ProductEditionStatus::Available)
                    ->lockForUpdate()
                    ->orderBy('number')
                    ->limit($quantity)
                    ->get();

                if ($editions->count() < $quantity) {
                    return ['error' => "Insufficient available editions for SKU {$skuCode}.", 'groups' => []];
                }
            }

            $allocatedGroups[] = [
                'lines' => $group['lines'],
                'sku' => $sku,
                'editions' => $editions,
                'quantity' => $quantity,
            ];
        }

        return ['error' => null, 'groups' => $allocatedGroups];
    }

    /**
     * @param  array<int, array{lines: array<int, NormalizedLineItemData>, sku: ProductSku, editions: \Illuminate\Support\Collection<int, ProductEdition>, quantity: int}>  $allocatedGroups
     */
    private function createAllocatedOrder(StorefrontConnection $connection, NormalizedOrderData $normalized, array $allocatedGroups): Order
    {
        $order = $this->createBaseOrder($connection, $normalized, OrderStatus::Paid);

        foreach ($allocatedGroups as $allocated) {
            /** @var ProductSku $sku */
            $sku = $allocated['sku'];

            if (! $sku->allocatePaidQuantity((int) $allocated['quantity'])) {
                throw new \RuntimeException("Unable to allocate stock for SKU {$sku->sku_code}.");
            }

            $editions = $allocated['editions'];
            foreach ($allocated['lines'] as $line) {
                if ($editions->isEmpty()) {
                    $this->createOrderItem($order, $line, $sku, null);

                    continue;
                }

                for ($i = 0; $i < $line->quantity; $i++) {
                    $edition = $editions->shift();
                    if (! $edition) {
                        throw new \RuntimeException("Unable to allocate edition for SKU {$line->skuCode}.");
                    }

                    $edition->update([
                        'status' => ProductEditionStatus::Sold,
                        'product_sku_id' => $sku->id,
                    ]);

                    $this->createOrderItem($order, $line, $sku, $edition);
                }
            }
        }

        OrderEvent::create([
            'order_id' => $order->id,
            'type' => 'external_import_processed',
            'payload' => [
                'platform' => $connection->platform->value,
                'external_order_id' => $normalized->externalOrderId,
            ],
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'type' => 'stock_allocated',
            'payload' => ['line_count' => count($normalized->lineItems)],
        ]);

        return $order;
    }

    private function createExceptionOrder(StorefrontConnection $connection, NormalizedOrderData $normalized, string $reason): Order
    {
        $order = $this->createBaseOrder($connection, $normalized, OrderStatus::Exception, $reason);

        foreach ($normalized->lineItems as $line) {
            $this->createOrderItem($order, $line, null, null);
        }

        OrderEvent::create([
            'order_id' => $order->id,
            'type' => 'external_import_exception',
            'payload' => [
                'reason' => $reason,
                'platform' => $connection->platform->value,
                'external_order_id' => $normalized->externalOrderId,
            ],
        ]);

        return $order;
    }

    private function createBaseOrder(StorefrontConnection $connection, NormalizedOrderData $normalized, OrderStatus $status, ?string $exceptionReason = null): Order
    {
        $address = $normalized->shippingAddress;

        return Order::create([
            'storefront_connection_id' => $connection->id,
            'source_platform' => $connection->platform->value,
            'external_order_id' => $normalized->externalOrderId,
            'external_order_number' => $normalized->externalOrderNumber,
            'source_payment_status' => $normalized->paymentStatus,
            'source_fulfilment_status' => $normalized->fulfilmentStatus,
            'status' => $status,
            'currency' => strtolower($normalized->currency),
            'subtotal_amount' => $normalized->subtotalAmount,
            'shipping_amount' => $normalized->shippingAmount,
            'tax_amount' => $normalized->taxAmount,
            'total_amount' => $normalized->totalAmount,
            'customer_email' => $normalized->customerEmail,
            'shipping_name' => $address->name,
            'shipping_phone' => $address->phone,
            'shipping_line1' => $address->line1,
            'shipping_line2' => $address->line2,
            'shipping_city' => $address->city,
            'shipping_state' => $address->state,
            'shipping_postal_code' => $address->postalCode,
            'shipping_country' => $address->country ? strtoupper($address->country) : null,
            'paid_at' => $status === OrderStatus::Paid ? now() : null,
            'exception_reason' => $exceptionReason,
            'meta' => $normalized->meta ?: null,
        ]);
    }

    private function createOrderItem(Order $order, NormalizedLineItemData $line, ?ProductSku $sku, ?ProductEdition $edition): void
    {
        $quantity = $edition ? 1 : $line->quantity;

        $order->items()->create([
            'product_id' => $sku?->product_id,
            'product_edition_id' => $edition?->id,
            'product_sku_id' => $sku?->id,
            'product_name' => $line->productTitle,
            'product_slug' => $sku?->product?->slug,
            'variant_title_snapshot' => $line->variantTitle,
            'sku_code_snapshot' => $line->skuCode,
            'quantity' => $quantity,
            'unit_amount' => $line->unitAmount,
            'line_total_amount' => $edition ? $line->unitAmount : $line->lineTotal,
            'attributes_snapshot' => $line->attributes,
        ]);
    }

    private function notifyAdmins(Order $order): void
    {
        $admins = User::query()->where('role', 'admin')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ExternalOrderExceptionNotification($order));
        }
    }
}
