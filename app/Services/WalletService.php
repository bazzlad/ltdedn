<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

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
            return encrypt($privateKey);
        }

        $normalized = Str::startsWith($masterKey, 'base64:')
            ? base64_decode(substr($masterKey, 7), true)
            : $masterKey;

        if (! is_string($normalized) || $normalized === '') {
            throw new \RuntimeException('Invalid WALLET_MASTER_KEY configuration.');
        }

        $key = hash('sha256', $normalized, true);
        $encrypter = new Encrypter($key, config('app.cipher', 'AES-256-CBC'));

        return $encrypter->encryptString($privateKey);
    }
}
