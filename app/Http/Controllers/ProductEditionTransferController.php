<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductEditionTransferResource;
use App\Models\ProductEditionTransfer;
use Inertia\Inertia;
use Inertia\Response;

class ProductEditionTransferController extends Controller
{
    public function show(string $token): Response
    {
        $transfer = ProductEditionTransfer::where('token', $token)
            ->with(['productEdition.product.artist', 'sender', 'recipient'])
            ->firstOrFail();

        return Inertia::render('Transfers/Accept', [
            'transfer' => new ProductEditionTransferResource($transfer),
        ]);
    }
}
