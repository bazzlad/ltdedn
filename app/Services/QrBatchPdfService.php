<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductEdition;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class QrBatchPdfService
{
    private const QR_SIZE = 1400;

    private const QR_MARGIN = 0;

    private const PDF_PAPER = 'A4';

    private const PDF_ORIENTATION = 'portrait';

    public function __construct(
        private QRCodeService $qrCodeService
    ) {}

    public function generatePdf(Request $request, Product $product, array $editionIds = []): array
    {
        $editions = $this->getEditions($product, $editionIds);

        if ($editions->isEmpty()) {
            return ['success' => false, 'error' => 'No QR codes available for the requested editions.'];
        }

        $qrItems = $this->generateQrItems($request, $product, $editions);

        if (empty($qrItems)) {
            return ['success' => false, 'error' => 'No valid QR codes could be generated.'];
        }

        $pdf = $this->createPdf($product, $qrItems);
        $filename = $this->generateFilename($product);

        return [
            'success' => true,
            'pdf' => $pdf,
            'filename' => $filename,
        ];
    }

    private function getEditions(Product $product, array $editionIds): Collection
    {
        $query = ProductEdition::where('product_id', $product->id)->orderBy('number');

        if (! empty($editionIds)) {
            $query->whereIn('id', $editionIds);
        }

        return $query->get();
    }

    private function generateQrItems(Request $request, Product $product, Collection $editions): array
    {
        $product->loadMissing('artist:id,name');
        $artistName = $product->artist->name ?? 'Unknown';
        $items = [];

        foreach ($editions as $edition) {
            $qrUrl = $this->buildQrUrl($request, $edition);
            if (! $qrUrl) {
                continue;
            }

            $qrCodeData = $this->qrCodeService->generateQrCodeImage(
                $qrUrl,
                self::QR_SIZE,
                self::QR_MARGIN
            );

            $items[] = [
                'label' => "{$artistName} · {$product->name} · #{$edition->number}",
                'sub' => $qrUrl,
                'img' => 'data:image/png;base64,'.base64_encode($qrCodeData),
            ];
        }

        return $items;
    }

    private function createPdf(Product $product, array $items): Dompdf
    {
        $html = $this->renderPdfView($product, $items);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper(self::PDF_PAPER, self::PDF_ORIENTATION);
        $pdf->render();

        return $pdf;
    }

    private function renderPdfView(Product $product, array $items): string
    {
        return view('admin.editions.qr-batch-pdf', [
            'product' => $product,
            'items' => $items,
        ])->render();
    }

    private function generateFilename(Product $product): string
    {
        return "product-{$product->id}-qrs.pdf";
    }

    private function buildQrUrl(Request $request, ProductEdition $edition): ?string
    {
        $code = $edition->qr_short_code ?: $edition->qr_code;

        if (! $code) {
            return null;
        }

        if (preg_match('#^https?://#i', $code)) {
            return $code;
        }

        return url("/qr/{$code}");
    }

    public function parseEditionIds(mixed $ids): array
    {
        if (is_string($ids)) {
            return array_filter(array_map('intval', preg_split('/[,\s]+/', $ids)));
        }

        return is_array($ids) ? $ids : [];
    }
}
