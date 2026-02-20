<?php

namespace App\Http\Controllers;

use App\Models\ChainToken;
use App\Services\ChainService;
use Illuminate\Http\JsonResponse;

class TokenMetadataController extends Controller
{
    public function __construct(private ChainService $chainService) {}

    public function __invoke(string $tokenId): JsonResponse
    {
        $chainToken = ChainToken::where('token_id', $tokenId)->firstOrFail();
        $chainToken->loadMissing('edition.product.artist');

        return response()->json($this->chainService->tokenMetadata($chainToken->edition));
    }
}
