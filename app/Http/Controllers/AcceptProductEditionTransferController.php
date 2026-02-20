<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Models\ProductEditionTransfer;
use App\Notifications\ProductEditionTransferAccepted;
use App\Services\ChainService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AcceptProductEditionTransferController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private ChainService $chainService,
    ) {}

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $transfer = ProductEditionTransfer::where('token', $token)->firstOrFail();

        if ($transfer->recipient_id !== $user->id) {
            abort(403, 'You cannot accept this transfer.');
        }

        if ($transfer->isExpired()) {
            abort(403, 'This transfer has expired.');
        }

        if ($transfer->status !== 'pending') {
            $edition = $transfer->productEdition;

            return redirect()->route('qr.show', $edition->qr_code)
                ->with('error', 'Transfer is no longer pending.');
        }

        $edition = $transfer->productEdition;
        $qrCode = $edition->qr_code;
        $lockKey = "transfer_qr_{$qrCode}";

        $lock = Cache::lock($lockKey, 10);

        try {
            if (! $lock->block(3)) {
                return back()->with('error', 'This transfer is currently being processed.');
            }

            return DB::transaction(function () use ($transfer, $user, $qrCode) {
                $transfer->refresh();
                if ($transfer->status !== 'pending') {
                    return redirect()->route('qr.show', $qrCode)
                        ->with('error', 'Transfer is no longer pending.');
                }

                $edition = $transfer->productEdition;
                $edition->lockForUpdate();

                $fromWallet = $this->walletService->getOrCreateForUser($transfer->sender);
                $toWallet = $this->walletService->getOrCreateForUser($user);
                $this->chainService->transferEdition($edition, $fromWallet, $toWallet);

                $edition->update([
                    'owner_id' => $user->id,
                    'status' => ProductEditionStatus::Sold,
                ]);

                $transfer->update([
                    'status' => 'accepted',
                    'completed_at' => now(),
                ]);

                $transfer->sender->notify(new ProductEditionTransferAccepted($transfer));

                return redirect()->route('qr.show', $qrCode)
                    ->with('success', 'Transfer accepted! The edition is now in your collection.');
            });
        } finally {
            $lock?->release();
        }
    }
}
