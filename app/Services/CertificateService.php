<?php

namespace App\Services;

use App\Models\ProductEdition;
use App\Models\User;
use Dompdf\Dompdf;

class CertificateService
{
    public function __construct(private QrImageService $qrImageService) {}

    public function renderPdf(ProductEdition $edition, User $owner): string
    {
        $edition->loadMissing(['product.artist', 'chainToken']);

        $verifyUrl = route('verify.qr', $edition->qr_code);
        $qrPng = $this->qrImageService->pngForUrl($verifyUrl);
        $qrBase64 = base64_encode($qrPng);

        $html = view('certificates.edition', [
            'edition' => $edition,
            'owner' => $owner,
            'verifyUrl' => $verifyUrl,
            'qrBase64' => $qrBase64,
        ])->render();

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
