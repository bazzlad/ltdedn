<?php

namespace App\Http\Controllers;

use App\Models\CertificateEvent;
use App\Services\QRCodeService;
use Illuminate\View\View;

class VerifyEditionController extends Controller
{
    public function __construct(private QRCodeService $qrService) {}

    public function __invoke(string $qrCode): View
    {
        $edition = $this->qrService->findEditionByQRCode($qrCode);

        abort_if(! $edition, 404);

        $edition->loadMissing(['product.artist', 'owner', 'chainToken']);

        CertificateEvent::create([
            'edition_id' => $edition->id,
            'user_id' => auth()->id(),
            'event_type' => 'verified',
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        return view('verify', [
            'edition' => $edition,
        ]);
    }
}
