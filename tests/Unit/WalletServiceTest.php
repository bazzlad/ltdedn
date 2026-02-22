<?php

namespace Tests\Unit;

use App\Services\WalletService;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    public function test_encrypt_and_decrypt_round_trip_with_master_key(): void
    {
        config()->set('blockchain.wallet_master_key', 'test-master-key');
        config()->set('blockchain.encryption_version', 1);

        $service = app(WalletService::class);
        $privateKey = bin2hex(random_bytes(32));

        $encrypted = $service->encryptPrivateKey($privateKey);
        $decrypted = $service->decryptPrivateKey($encrypted, 1);

        $this->assertNotSame($privateKey, $encrypted);
        $this->assertSame($privateKey, $decrypted);
    }

    public function test_decrypt_throws_for_unsupported_version(): void
    {
        config()->set('blockchain.wallet_master_key', 'test-master-key');

        $service = app(WalletService::class);
        $encrypted = $service->encryptPrivateKey('abc123');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported wallet encryption version');

        $service->decryptPrivateKey($encrypted, 2);
    }
}
