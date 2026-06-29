<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEdition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductEditionCsvController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Product $product): StreamedResponse
    {
        $this->authorize('view', $product);
        $this->authorize('viewAny', ProductEdition::class);

        $validated = $request->validate([
            'logo' => ['nullable', 'string', 'max:255'],
        ]);

        $logo = $validated['logo'] ?? '';
        $total = $product->editions()->count();
        $filename = sprintf(
            'product-%d-%s-qr-codes.csv',
            $product->id,
            Str::slug($product->name) ?: 'product'
        );

        return response()->streamDownload(function () use ($product, $logo, $total): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['QR', 'LOGO', 'EDN', 'TOTAL']);

            $product->editions()
                ->orderBy('number')
                ->each(function (ProductEdition $edition) use ($handle, $logo, $total): void {
                    fputcsv($handle, [
                        $this->buildQrUrl($edition),
                        $logo,
                        str_pad((string) $edition->number, strlen((string) $total), '0', STR_PAD_LEFT),
                        $total,
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildQrUrl(ProductEdition $edition): string
    {
        $code = $edition->getAttribute('qr_short_code') ?: $edition->qr_code;

        if (preg_match('#^https?://#i', $code)) {
            return $code;
        }

        return url("/qr/{$code}");
    }
}
