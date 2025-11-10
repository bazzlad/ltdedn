<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Services\QRCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClaimQRController extends Controller
{
    public function __construct(private QRCodeService $qrService) {}

    public function __invoke(Request $request, string $qrCode): RedirectResponse
    {
        if (! Auth::check()) {
            session(['url.intended' => route('qr.show', $qrCode)]);

            return redirect()->route('login')
                ->with('info', 'Please log in to claim this edition.');
        }

        $user = Auth::user();

        $edition = $this->qrService->findEditionByQRCode($qrCode);

        if (! $edition) {
            return redirect()->route('qr.show', $qrCode)
                ->with('error', 'Edition not found.');
        }

        if ($edition->owner_id && $edition->owner_id === $user->id) {
            return redirect()->route('qr.show', $qrCode)
                ->with('info', 'You already own this edition.');
        }

        if ($edition->owner_id) {
            return redirect()->route('qr.show', $qrCode)
                ->with('error', 'This edition has already been claimed by someone else.');
        }

        if (!$edition->isAvailable()) {
            return redirect()->route('qr.show', $qrCode)
                ->with('error', 'This edition is not available for claiming.');
        }

        $edition->update([
            'owner_id' => $user->id,
            'status' => ProductEditionStatus::Sold,
        ]);

        return redirect()->route('qr.show', $qrCode)
            ->with('success', "Congratulations! You now own edition #{$edition->number} of \"{$edition->product->name}\".");
    }
}
