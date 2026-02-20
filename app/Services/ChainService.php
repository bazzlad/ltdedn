<?php

namespace App\Services;

use App\Models\ChainToken;
use App\Models\ProductEdition;
use App\Models\Wallet;

class ChainService
{
    public function mintEditionToUserWallet(ProductEdition $edition, Wallet $wallet): ChainToken
    {
        $existing = ChainToken::where('edition_id', $edition->id)->first();
        if ($existing) {
            return $existing;
        }

        $tokenId = (string) $edition->id;
        $txHash = $this->mockTxHash();

        return ChainToken::create([
            'edition_id' => $edition->id,
            'chain' => 'polygon',
            'contract_address' => (string) config('blockchain.contract_address'),
            'token_id' => $tokenId,
            'mint_tx_hash' => $txHash,
            'last_tx_hash' => $txHash,
            'minted_at' => now(),
        ]);
    }

    public function transferEdition(ProductEdition $edition, Wallet $fromWallet, Wallet $toWallet): ?string
    {
        $chainToken = ChainToken::where('edition_id', $edition->id)->first();
        if (! $chainToken) {
            return null;
        }

        $txHash = $this->mockTxHash();

        $chainToken->update([
            'last_tx_hash' => $txHash,
        ]);

        return $txHash;
    }

    public function tokenMetadata(ProductEdition $edition): array
    {
        $edition->loadMissing(['product.artist', 'chainToken']);

        return [
            'name' => $edition->product->name.' #'.$edition->number,
            'description' => 'Authenticated edition for '.$edition->product->name,
            'artist' => $edition->product->artist->name,
            'edition_number' => $edition->number,
            'image' => $edition->product->cover_image_url,
            'verify_url' => route('verify.qr', $edition->qr_code),
            'attributes' => [
                ['trait_type' => 'Product', 'value' => $edition->product->name],
                ['trait_type' => 'Artist', 'value' => $edition->product->artist->name],
                ['trait_type' => 'Edition Number', 'value' => $edition->number],
            ],
        ];
    }

    private function mockTxHash(): string
    {
        return '0x'.bin2hex(random_bytes(32));
    }
}
