<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;

class WalletService
{
    public function getOrCreateForUser(User $user): Wallet
    {
        $existing = Wallet::where('user_id', $user->id)
            ->where('chain', 'polygon')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createWallet($user);
    }

    public function createWallet(User $user): Wallet
    {
        $privateKey = bin2hex(random_bytes(32));
        $address = '0x'.substr(hash('sha256', $privateKey), 0, 40);

        return Wallet::create([
            'user_id' => $user->id,
            'chain' => 'polygon',
            'address' => $address,
            'encrypted_private_key' => $this->encryptPrivateKey($privateKey),
            'encryption_version' => (int) config('blockchain.encryption_version', 1),
        ]);
    }

    public function encryptPrivateKey(string $privateKey): string
    {
        $masterKey = (string) config('blockchain.wallet_master_key');

        if ($masterKey === '') {
            throw new \RuntimeException('WALLET_MASTER_KEY is not configured.');
        }

        return encrypt($privateKey);
    }
}
