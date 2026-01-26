<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductEditionResource;
use App\Http\Resources\ProductEditionTransferResource;
use App\Models\ProductEdition;
use App\Models\ProductEditionTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = Auth::user();

        $ownedEditions = ProductEdition::with(['product.artist'])
            ->where('owner_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $pendingTransfers = ProductEditionTransfer::with(['productEdition.product.artist', 'sender'])
            ->where('recipient_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('UserDashboard', [
            'ownedEditions' => ProductEditionResource::collection($ownedEditions),
            'pendingTransfers' => ProductEditionTransferResource::collection($pendingTransfers),
        ]);
    }
}
