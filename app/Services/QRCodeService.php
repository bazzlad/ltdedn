<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductEdition;

class QRCodeService
{
    /**
     * Salt used for deterministic QR code generation.
     */
    private const QR_SALT = 'ltdedn_qr_salt_2025';

    /**
     * Generate a deterministic QR code for a product edition.
     *
     * The QR code is generated using a combination of:
     * - Product ID
     * - Edition number
     * - Product slug (for readability)
     * - Salt (for security)
     */
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

    /**
     * Generate a deterministic short QR code for a product edition.
     *
     * This creates a shorter, more user-friendly code while maintaining
     * deterministic behavior based on the same inputs.
     */
    public function generateShortQRCode(Product $product, int $editionNumber): string
    {
        $baseString = implode('|', [
            $product->id,
            $editionNumber,
            $product->slug,
            self::QR_SALT,
            'short',
        ]);

        $hash = hash('sha256', $baseString);

        // Take first 8 characters and make uppercase for readability
        return strtoupper(substr($hash, 0, 8));
    }

    /**
     * Generate both QR codes for a product edition.
     *
     * @return array{qr_code: string, qr_short_code: string}
     */
    public function generateQRCodes(Product $product, int $editionNumber): array
    {
        return [
            'qr_code' => $this->generateQRCode($product, $editionNumber),
            'qr_short_code' => $this->generateShortQRCode($product, $editionNumber),
        ];
    }

    /**
     * Generate QR codes for an existing ProductEdition model.
     *
     * @return array{qr_code: string, qr_short_code: string}
     */
    public function generateQRCodesForEdition(ProductEdition $edition): array
    {
        $edition->load('product');

        return $this->generateQRCodes($edition->product, $edition->number);
    }

    /**
     * Verify if a QR code is valid for a given product edition.
     */
    public function verifyQRCode(Product $product, int $editionNumber, string $qrCode): bool
    {
        $expectedQRCode = $this->generateQRCode($product, $editionNumber);

        return hash_equals($expectedQRCode, $qrCode);
    }

    /**
     * Verify if a short QR code is valid for a given product edition.
     */
    public function verifyShortQRCode(Product $product, int $editionNumber, string $shortQRCode): bool
    {
        $expectedShortQRCode = $this->generateShortQRCode($product, $editionNumber);

        return hash_equals($expectedShortQRCode, $shortQRCode);
    }

    /**
     * Generate a URL-safe QR code that can be used in web applications.
     */
    public function generateQRCodeUrl(Product $product, int $editionNumber, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('app.url');
        $qrCode = $this->generateQRCode($product, $editionNumber);

        return "{$baseUrl}/qr/{$qrCode}";
    }

    /**
     * Parse a QR code to extract product and edition information.
     * This is useful for reverse lookups.
     *
     * Note: This requires a database lookup since QR codes are hashed.
     */
    public function findEditionByQRCode(string $qrCode): ?ProductEdition
    {
        return ProductEdition::where('qr_code', $qrCode)
            ->with('product')
            ->first();
    }

    /**
     * Parse a short QR code to extract product and edition information.
     */
    public function findEditionByShortQRCode(string $shortQRCode): ?ProductEdition
    {
        return ProductEdition::where('qr_short_code', $shortQRCode)
            ->with('product')
            ->first();
    }
}
