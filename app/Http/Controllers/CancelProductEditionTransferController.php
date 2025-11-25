<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Models\ProductEditionTransfer;
use App\Notifications\ProductEditionTransferCancelled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CancelProductEditionTransferController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $transfer = ProductEditionTransfer::where('token', $token)->firstOrFail();

        if ($transfer->sender_id !== $user->id) {
            abort(403, 'You cannot cancel this transfer.');
        }

        if ($transfer->status !== 'pending') {
            return back()->with('error', 'Transfer is not pending.');
        }

        $edition = $transfer->productEdition;
        $qrCode = $edition->qr_code;
        $lockKey = "transfer_qr_{$qrCode}";
        $lock = Cache::lock($lockKey, 10);

        try {
            if (! $lock->block(3)) {
                return back()->with('error', 'Currently processing. Try again.');
            }

            return DB::transaction(function () use ($transfer, $qrCode) {
                $transfer->refresh();
                if ($transfer->status !== 'pending') {
                    return redirect()->route('qr.show', $qrCode)->with('error', 'Transfer is not pending.');
                }

                $edition = $transfer->productEdition;
                $edition->lockForUpdate();

                $edition->update([
                    'status' => ProductEditionStatus::Sold,
                ]);

                $transfer->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                ]);

                $transfer->recipient->notify(new ProductEditionTransferCancelled($transfer));

                return redirect()->route('qr.show', $qrCode)
                    ->with('success', 'Transfer cancelled.');
            });
        } finally {
            $lock?->release();
        }
    }
}
