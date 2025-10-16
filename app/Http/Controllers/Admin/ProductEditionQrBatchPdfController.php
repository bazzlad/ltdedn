<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Services\QrBatchPdfService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductEditionQrBatchPdfController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private QrBatchPdfService $qrBatchPdfService
    ) {}

    public function __invoke(Request $request, Product $product): RedirectResponse|StreamedResponse
    {
        $this->authorize('view', $product);
        $this->authorize('viewAny', ProductEdition::class);

        $editionIds = $this->qrBatchPdfService->parseEditionIds($request->input('edition_ids', []));

        $result = $this->qrBatchPdfService->generatePdf($request, $product, $editionIds);

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        return response()->streamDownload(
            fn () => print ($result['pdf']->output()),
            $result['filename'],
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            ]
        );
    }
}
