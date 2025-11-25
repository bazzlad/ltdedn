<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Http\Resources\ProductEditionResource;
use App\Models\ProductEditionTransfer;
use App\Services\QRCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowQRController extends Controller
{
    public function __construct(private QRCodeService $qrService) {}

    public function __invoke(string $qrCode): Response|RedirectResponse
    {
        $edition = $this->qrService->findEditionByQRCode($qrCode);

        if (! $edition) {
            return Inertia::render('QR/NotFound', [
                'qrCode' => $qrCode,
            ]);
        }

        $edition->load(['product.artist', 'owner']);

        $isClaimed = ! is_null($edition->owner_id);
        $isOwnedByCurrentUser = $isClaimed && Auth::check() && $edition->owner_id === Auth::id();

        $activeTransfer = null;
        if ($isOwnedByCurrentUser && $edition->status === ProductEditionStatus::PendingTransfer) {
            $activeTransfer = ProductEditionTransfer::where('product_edition_id', $edition->id)
                ->where('status', 'pending')
                ->with('recipient')
                ->latest()
                ->first();
        }

        return Inertia::render('QR/Claim', [
            'edition' => new ProductEditionResource($edition),
            'isClaimed' => $isClaimed,
            'isOwnedByCurrentUser' => $isOwnedByCurrentUser,
            'canClaim' => ! $isClaimed && $edition->status === ProductEditionStatus::Available,
            'activeTransfer' => $activeTransfer,
        ]);
    }
}
