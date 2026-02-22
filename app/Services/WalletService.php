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
        $address = $this->generateAddressForPrivateKey($privateKey);

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

    public function decryptPrivateKey(string $encryptedPrivateKey, ?int $encryptionVersion = null): string
    {
        $version = $encryptionVersion ?? (int) config('blockchain.encryption_version', 1);

        if ($version !== 1) {
            throw new \RuntimeException('Unsupported wallet encryption version: '.$version);
        }

        $masterKey = (string) config('blockchain.wallet_master_key');

        if ($masterKey === '') {
            return decrypt($encryptedPrivateKey);
        }

        $normalized = Str::startsWith($masterKey, 'base64:')
            ? base64_decode(substr($masterKey, 7), true)
            : $masterKey;

        if (! is_string($normalized) || $normalized === '') {
            throw new \RuntimeException('Invalid WALLET_MASTER_KEY configuration.');
        }

        $key = hash('sha256', $normalized, true);
        $encrypter = new Encrypter($key, config('app.cipher', 'AES-256-CBC'));

        return $encrypter->decryptString($encryptedPrivateKey);
    }

    private function generateAddressForPrivateKey(string $privateKey): string
    {
        $allowPlaceholder = (bool) config('blockchain.allow_placeholder_address_derivation', true);

        if (! $allowPlaceholder) {
            throw new \RuntimeException('Placeholder wallet address derivation is disabled. Configure EVM derivation before production use.');
        }

        return '0x'.substr(bin2hex(random_bytes(20)), 0, 40);
    }
}
