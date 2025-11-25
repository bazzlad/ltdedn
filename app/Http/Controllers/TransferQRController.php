<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Models\ProductEditionTransfer;
use App\Models\User;
use App\Notifications\QRCodeTransferred;
use App\Services\QRCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        $lockKey = "transfer_qr_{$qrCode}";
        $lock = Cache::lock($lockKey, 10);

        try {
            if (! $lock->block(3)) {
                return redirect()->route('qr.show', $qrCode)
                    ->with('error', 'This edition is currently being processed. Please try again.');
            }

            return DB::transaction(function () use ($qrCode, $user, $request) {
                $edition = $this->qrService->findEditionByQRCode($qrCode);

                if (! $edition) {
                    return redirect()->route('qr.show', $qrCode)
                        ->with('error', 'Edition not found.');
                }

                $edition->lockForUpdate();
                $edition->load('product.artist');

                if ($edition->owner_id !== $user->id) {
                    return redirect()->route('qr.show', $qrCode)
                        ->with('error', 'You do not own this edition.');
                }

                if ($edition->status === ProductEditionStatus::PendingTransfer) {
                    return redirect()->route('qr.show', $qrCode)
                        ->with('error', 'This edition is already pending transfer.');
                }

                $recipient = User::where('email', $request->recipient_email)->first();

                if ($recipient->id === $user->id) {
                    return redirect()->route('qr.show', $qrCode)
                        ->with('info', 'You already own this edition.');
                }

                $transfer = ProductEditionTransfer::create([
                    'product_edition_id' => $edition->id,
                    'sender_id' => $user->id,
                    'recipient_id' => $recipient->id,
                    'expires_at' => now()->addHours(48),
                    'status' => 'pending',
                ]);

                $edition->update([
                    'status' => ProductEditionStatus::PendingTransfer,
                ]);

                $recipient->notify(new QRCodeTransferred($edition, $user, $transfer->token));

                return redirect()->route('qr.show', $qrCode)
                    ->with('success', "Transfer request sent to {$recipient->name}. They have 48 hours to accept.");
            });
        } finally {
            $lock?->release();
        }
    }
}
