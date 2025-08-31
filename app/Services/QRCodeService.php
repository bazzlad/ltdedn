<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductEdition;

class QRCodeService
{
    private const QR_SALT = 'ltdedn_qr_salt_2025';

    public function generateQRCode(Product $product, int $editionNumber): string
    {
        $baseString = implode('|', [
            $product->id,
            $editionNumber,
            $product->slug,
            self::QR_SALT,
        ]);

        return hash('sha256', $baseString);
    }

    public function generateQRCodeForEdition(ProductEdition $edition): string
    {
        $edition->load('product');

        return $this->generateQRCode($edition->product, $edition->number);
    }

    public function verifyQRCode(Product $product, int $editionNumber, string $qrCode): bool
    {
        $expectedQRCode = $this->generateQRCode($product, $editionNumber);

        return hash_equals($expectedQRCode, $qrCode);
    }

    public function generateQRCodeUrl(Product $product, int $editionNumber, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('app.url');
        $qrCode = $this->generateQRCode($product, $editionNumber);

        return "{$baseUrl}/qr/{$qrCode}";
    }

    public function findEditionByQRCode(string $qrCode): ?ProductEdition
    {
        return ProductEdition::where('qr_code', $qrCode)
            ->with('product')
            ->first();
    }
}
