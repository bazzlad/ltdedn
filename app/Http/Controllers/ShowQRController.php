<?php

namespace App\Http\Controllers;

use App\Enums\ProductEditionStatus;
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

        return Inertia::render('QR/Claim', [
            'edition' => [
                'id' => $edition->id,
                'number' => $edition->number,
                'status' => $edition->status,
                'qr_code' => $edition->qr_code,
                'created_at' => $edition->created_at,
                'product' => [
                    'id' => $edition->product->id,
                    'name' => $edition->product->name,
                    'slug' => $edition->product->slug,
                    'description' => $edition->product->description,
                    'cover_image_url' => $edition->product->cover_image_url,
                    'artist' => [
                        'id' => $edition->product->artist->id,
                        'name' => $edition->product->artist->name,
                    ],
                ],
                'owner' => null,
            ],
            'isClaimed' => $isClaimed,
            'isOwnedByCurrentUser' => $isOwnedByCurrentUser,
            'canClaim' => ! $isClaimed && $edition->status === ProductEditionStatus::Available,
        ]);
    }
}
