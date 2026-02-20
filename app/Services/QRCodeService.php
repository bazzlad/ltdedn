<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductEdition;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QRCodeService
{
    private const LOGO_PNG_PATH = 'images/logo-white.png';

    private const DEFAULT_QR_SIZE = 1400;

    private const DEFAULT_QR_MARGIN = 0;

    private const DEFAULT_LOGO_WIDTH = 400;

    private const DEFAULT_LOGO_HEIGHT = 400;

    public function generateQRCode(?Product $product = null, ?int $editionNumber = null): string
    {
        return bin2hex(random_bytes(32));
    }

    public function generateQRCodeForEdition(ProductEdition $edition): string
    {
        if (! empty($edition->qr_code)) {
            return $edition->qr_code;
        }

        return $this->generateQRCode();
    }

    public function verifyQRCode(Product $product, int $editionNumber, string $qrCode): bool
    {
        $edition = ProductEdition::where('product_id', $product->id)
            ->where('number', $editionNumber)
            ->first();

        if (! $edition || empty($edition->qr_code)) {
            return false;
        }

        return hash_equals($edition->qr_code, $qrCode);
    }

    public function generateQRCodeUrl(Product $product, int $editionNumber, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? config('app.url');

        $edition = ProductEdition::where('product_id', $product->id)
            ->where('number', $editionNumber)
            ->first();

        $qrCode = $edition && ! empty($edition->qr_code)
            ? $edition->qr_code
            : $this->generateQRCode($product, $editionNumber);

        return "{$baseUrl}/qr/{$qrCode}";
    }

    public function findEditionByQRCode(string $qrCode): ?ProductEdition
    {
        return ProductEdition::where('qr_code', $qrCode)
            ->with('product')
            ->first();
    }

    public function generateQrCodeImage(
        string $url,
        int $size = self::DEFAULT_QR_SIZE,
        int $margin = self::DEFAULT_QR_MARGIN,
        int $logoWidth = self::DEFAULT_LOGO_WIDTH,
        int $logoHeight = self::DEFAULT_LOGO_HEIGHT
    ): string {
        $logoPath = public_path(self::LOGO_PNG_PATH);

        $writer = new PngWriter;

        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        $logo = new Logo(
            path: $logoPath,
            resizeToWidth: $logoWidth,
            resizeToHeight: $logoHeight,
            punchoutBackground: true
        );

        $result = $writer->write($qrCode, $logo);

        return $result->getString();
    }
}
