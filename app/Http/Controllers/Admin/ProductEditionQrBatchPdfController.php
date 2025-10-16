<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEdition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;

class ProductEditionQrBatchPdfController extends Controller
{
    use AuthorizesRequests;
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Product $product)
    {
        $this->authorize('view', $product);
        $this->authorize('viewAny', ProductEdition::class);

        $ids = $request->input('edition_ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $ids)));
        }
        if (! is_array($ids)) {
            $ids = [];
        }

        $query = ProductEdition::where('product_id', $product->id)->orderBy('number');
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }
        $editions = $query->get();

        if ($editions->isEmpty()) {
            return back()->with('error', 'No QR codes available for the requested editions.');
        }

        $product->loadMissing('artist:id,name');
        $artist = $product->artist->name ?? 'Unknown';
        $writer = new PngWriter;
        $items = [];

        foreach ($editions as $edition) {
            $qrUrl = $this->buildQrUrl($request, $edition);
            if (! $qrUrl) {
                continue;
            }

            $qrCode = QrCode::create($qrUrl)
                ->setEncoding(new Encoding('UTF-8'))
                ->setSize(1400)
                ->setMargin(0);

            $result = $writer->write($qrCode);
            $pngData = $result->getString();

            $items[] = [
                'label' => "{$artist} · {$product->name} · #{$edition->number}",
                'sub' => $qrUrl,
                'img' => 'data:image/png;base64,'.base64_encode($pngData),
            ];
        }

        if (empty($items)) {
            return back()->with('error', 'No valid QR codes could be generated.');
        }

        $html = view('admin.editions.qr-batch-pdf', [
            'product' => $product,
            'items' => $items,
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = "product-{$product->id}-qrs.pdf";

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    private function buildQrUrl(Request $request, ProductEdition $ed): ?string
    {
        $code = $ed->qr_short_code ?: $ed->qr_code;
        if (! $code) {
            return null;
        }

        // if already absolute, use as is (should't happen in practice)
        if (preg_match('#^https?://#i', $code)) {
            return $code;
        }

        // build absolute URL
        $scheme = $request->isSecure() ? 'https' : 'http';
        $host = $request->getHost();
        $port = $request->getPort();
        $portStr = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$portStr}/qr/{$code}";
    }
}
