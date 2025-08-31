<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\QRCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransferQRController extends Controller
{
    public function __construct(private QRCodeService $qrService) {}

    public function __invoke(Request $request, string $qrCode): RedirectResponse
    {
        $request->validate([
            'recipient_email' => 'required|email|exists:users,email',
        ]);

        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        $edition = $this->qrService->findEditionByQRCode($qrCode);

        if (! $edition) {
            return redirect()->route('qr.show', $qrCode)
                ->with('error', 'Edition not found.');
        }

        if ($edition->owner_id !== $user->id) {
            return redirect()->route('qr.show', $qrCode)
                ->with('error', 'You do not own this edition.');
        }

        $recipient = User::where('email', $request->recipient_email)->first();

        $edition->update([
            'owner_id' => $recipient->id,
            'status' => 'pending_transfer',
        ]);

        $edition->update(['status' => 'sold']);

        return redirect()->route('qr.show', $qrCode)
            ->with('success', "Edition successfully transferred to {$recipient->name} ({$recipient->email}).");
    }
}
