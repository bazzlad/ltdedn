<?php

namespace App\Http\Controllers;

use App\Models\CertificateEvent;
use App\Models\ProductEdition;
use App\Services\CertificateService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificateService) {}

    public function __invoke(ProductEdition $edition): Response
    {
        $user = Auth::user();

        abort_if(! $user, 401);
        abort_if((int) $edition->owner_id !== (int) $user->id, 403);

        $pdf = $this->certificateService->renderPdf($edition, $user);

        CertificateEvent::create([
            'edition_id' => $edition->id,
            'user_id' => $user->id,
            'event_type' => 'certificate_downloaded',
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-edition-'.$edition->id.'.pdf"',
        ]);
    }
}
