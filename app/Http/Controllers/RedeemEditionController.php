<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
use App\Models\CertificateEvent;
use App\Services\ChainService;
use App\Services\QRCodeService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RedeemEditionController extends Controller
{
    public function __construct(
        private QRCodeService $qrService,
        private WalletService $walletService,
        private ChainService $chainService,
    ) {}

    public function __invoke(string $qrCode): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        return DB::transaction(function () use ($user, $qrCode) {
            $edition = $this->qrService->findEditionByQRCode($qrCode);

            if (! $edition) {
                return redirect()->route('qr.show', $qrCode)->with('error', 'Edition not found.');
            }

            if ((int) $edition->owner_id !== (int) $user->id) {
                return redirect()->route('qr.show', $qrCode)->with('error', 'Only the owner can redeem this edition.');
            }

            $wallet = $this->walletService->getOrCreateForUser($user);
            $this->chainService->mintEditionToUserWallet($edition, $wallet);

            $edition->update([
                'status' => ProductEditionStatus::Redeemed,
                'owner_id' => $user->id,
            ]);

            CertificateEvent::create([
                'edition_id' => $edition->id,
                'user_id' => $user->id,
                'event_type' => 'redeemed',
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);

            return redirect()->route('qr.show', $qrCode)->with('success', 'Edition redeemed and minted on-chain.');
        });
    }
}
