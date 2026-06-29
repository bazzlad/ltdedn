<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductEditionStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEdition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductEditionSkuCsvController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Product $product): StreamedResponse
    {
        $this->authorize('view', $product);
        $this->authorize('viewAny', ProductEdition::class);

        $skuCodes = $product->skus()
            ->orderBy('sku_code')
            ->pluck('sku_code');

        $defaultSkuCode = $skuCodes->count() === 1 ? $skuCodes->first() : null;

        $filename = sprintf(
            'product-%d-%s-skus.csv',
            $product->id,
            Str::slug($product->name) ?: 'product'
        );

        return response()->streamDownload(function () use ($product, $defaultSkuCode): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['SKU', 'EDN', 'STATUS', 'SOLD']);

            $product->editions()
                ->with('sku')
                ->orderBy('number')
                ->each(function (ProductEdition $edition) use ($handle, $defaultSkuCode): void {
                    fputcsv($handle, [
                        $edition->sku?->sku_code ?? $defaultSkuCode ?? '',
                        $edition->number,
                        $edition->status->value,
                        $this->isSold($edition) ? 'Yes' : 'No',
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function isSold(ProductEdition $edition): bool
    {
        return in_array($edition->status, [
            ProductEditionStatus::Sold,
            ProductEditionStatus::Redeemed,
            ProductEditionStatus::PendingTransfer,
        ], true);
    }
}
