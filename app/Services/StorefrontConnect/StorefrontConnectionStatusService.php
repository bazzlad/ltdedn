<?php

namespace App\Services\StorefrontConnect;

use App\Enums\ExternalImportStatus;
use App\Enums\ProductEditionStatus;
use App\Enums\StorefrontConnectionStatus;
use App\Enums\StorefrontPlatform;
use App\Models\ExternalOrderImport;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\StorefrontConnection;

class StorefrontConnectionStatusService
{
    /**
     * @return list<array{
     *     sku_code: string,
     *     product_name: string,
     *     store_sku_found: bool|null,
     *     stock_available: int,
     *     editions_available: int,
     *     status: string
     * }>
     */
    public function skuChecklist(StorefrontConnection $connection): array
    {
        $externalSkus = collect(data_get($connection->last_sync_meta, 'external_skus', []))
            ->map(fn (mixed $sku): string => trim((string) $sku))
            ->filter()
            ->values();

        $hasExternalDiscovery = $externalSkus->isNotEmpty();

        return ProductSku::query()
            ->with('product:id,artist_id,name,is_limited')
            ->whereHas('product', fn ($query) => $query->where('artist_id', $connection->artist_id))
            ->where('is_active', true)
            ->orderBy('sku_code')
            ->get()
            ->map(function (ProductSku $sku) use ($externalSkus, $hasExternalDiscovery): array {
                $editionsAvailable = $sku->product?->is_limited
                    ? ProductEdition::query()
                        ->where('product_id', $sku->product_id)
                        ->where(function ($query) use ($sku): void {
                            $query->whereNull('product_sku_id')
                                ->orWhere('product_sku_id', $sku->id);
                        })
                        ->where('status', ProductEditionStatus::Available->value)
                        ->count()
                    : $sku->stock_available;
                $storeSkuFound = $hasExternalDiscovery ? $externalSkus->contains($sku->sku_code) : null;

                return [
                    'sku_code' => $sku->sku_code,
                    'product_name' => $sku->product?->name ?? 'Unknown product',
                    'store_sku_found' => $storeSkuFound,
                    'stock_available' => $sku->stock_available,
                    'editions_available' => $editionsAvailable,
                    'status' => $this->skuStatus($storeSkuFound, $sku->stock_available, $editionsAvailable),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     state: string,
     *     label: string,
     *     detail: string|null,
     *     import_id: int|null,
     *     order_id: int|null,
     *     last_successful_import: array{
     *         import_id: int,
     *         order_id: int|null,
     *         order_number: string|null,
     *         imported_at: string|null,
     *         pushback_status: string|null
     *     }|null
     * }
     */
    public function testOrderState(StorefrontConnection $connection): array
    {
        $latestImport = $connection->imports()
            ->latest()
            ->first();
        $lastSuccessfulImport = $this->lastSuccessfulImport($connection);

        if (! $latestImport) {
            return [
                'state' => 'waiting',
                'label' => 'Waiting for first paid test order',
                'detail' => null,
                'import_id' => null,
                'order_id' => null,
                'last_successful_import' => $lastSuccessfulImport,
            ];
        }

        return [
            'state' => $latestImport->status->value,
            'label' => $this->importLabel($latestImport),
            'detail' => $latestImport->error_details,
            'import_id' => $latestImport->id,
            'order_id' => $latestImport->order_id,
            'last_successful_import' => $lastSuccessfulImport,
        ];
    }

    public function markTestReceived(StorefrontConnection $connection): void
    {
        $connection->forceFill([
            'tested_at' => now(),
            'connection_status' => StorefrontConnectionStatus::Testing,
            'last_connection_error' => null,
        ])->save();
    }

    public function markSuccessfulTestOrder(StorefrontConnection $connection): void
    {
        if (! in_array($connection->platform, [StorefrontPlatform::Shopify, StorefrontPlatform::Squarespace], true)) {
            return;
        }

        $connection->forceFill([
            'tested_at' => $connection->tested_at ?? now(),
            'connection_status' => StorefrontConnectionStatus::Ready,
            'last_connection_error' => null,
        ])->save();
    }

    public function activate(StorefrontConnection $connection): void
    {
        $connection->forceFill([
            'connection_status' => StorefrontConnectionStatus::Ready,
            'activated_at' => now(),
            'last_connection_error' => null,
        ])->save();
    }

    private function skuStatus(?bool $storeSkuFound, int $stockAvailable, int $editionsAvailable): string
    {
        if ($storeSkuFound === false) {
            return 'missing_in_store';
        }

        if ($stockAvailable < 1 || $editionsAvailable < 1) {
            return 'no_stock';
        }

        return 'ready';
    }

    private function importLabel(ExternalOrderImport $import): string
    {
        return match ($import->status) {
            ExternalImportStatus::Processed => 'Test order imported',
            ExternalImportStatus::Ignored => 'Latest order was ignored',
            ExternalImportStatus::Exception => 'Latest order needs attention',
            ExternalImportStatus::Failed => 'Latest import failed',
            default => 'Latest import is pending',
        };
    }

    /**
     * @return array{import_id: int, order_id: int|null, order_number: string|null, imported_at: string|null, pushback_status: string|null}|null
     */
    private function lastSuccessfulImport(StorefrontConnection $connection): ?array
    {
        $import = $connection->imports()
            ->with('order:id,external_order_number,shipment_pushback_status')
            ->where('status', ExternalImportStatus::Processed->value)
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->first();

        if (! $import) {
            return null;
        }

        return [
            'import_id' => $import->id,
            'order_id' => $import->order_id,
            'order_number' => $import->order?->external_order_number,
            'imported_at' => $import->processed_at ? (string) $import->processed_at : null,
            'pushback_status' => $import->order?->shipment_pushback_status,
        ];
    }
}
