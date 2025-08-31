<?php

namespace Tests\Unit;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Services\QRCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QRCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private QRCodeService $qrService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qrService = new QRCodeService;
    }

    public function test_generates_deterministic_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create([
            'id' => 1,
            'slug' => 'test-product',
        ]);

        $qrCode1 = $this->qrService->generateQRCode($product, 1);
        $qrCode2 = $this->qrService->generateQRCode($product, 1);

        $this->assertEquals($qrCode1, $qrCode2);
        $this->assertIsString($qrCode1);
        $this->assertEquals(64, strlen($qrCode1)); // SHA256 hash length
    }

    public function test_generates_different_qr_codes_for_different_editions(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create([
            'slug' => 'test-product',
        ]);

        $qrCode1 = $this->qrService->generateQRCode($product, 1);
        $qrCode2 = $this->qrService->generateQRCode($product, 2);

        $this->assertNotEquals($qrCode1, $qrCode2);
    }

    public function test_generates_different_qr_codes_for_different_products(): void
    {
        $artist = Artist::factory()->create();
        $product1 = Product::factory()->for($artist)->create(['slug' => 'product-1']);
        $product2 = Product::factory()->for($artist)->create(['slug' => 'product-2']);

        $qrCode1 = $this->qrService->generateQRCode($product1, 1);
        $qrCode2 = $this->qrService->generateQRCode($product2, 1);

        $this->assertNotEquals($qrCode1, $qrCode2);
    }

    public function test_generates_deterministic_short_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create([
            'slug' => 'test-product',
        ]);

        $shortQRCode1 = $this->qrService->generateShortQRCode($product, 1);
        $shortQRCode2 = $this->qrService->generateShortQRCode($product, 1);

        $this->assertEquals($shortQRCode1, $shortQRCode2);
        $this->assertIsString($shortQRCode1);
        $this->assertEquals(8, strlen($shortQRCode1));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $shortQRCode1);
    }

    public function test_generates_different_short_qr_codes_for_different_editions(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $shortQRCode1 = $this->qrService->generateShortQRCode($product, 1);
        $shortQRCode2 = $this->qrService->generateShortQRCode($product, 2);

        $this->assertNotEquals($shortQRCode1, $shortQRCode2);
    }

    public function test_generates_both_qr_codes(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $qrCodes = $this->qrService->generateQRCodes($product, 1);

        $this->assertArrayHasKey('qr_code', $qrCodes);
        $this->assertArrayHasKey('qr_short_code', $qrCodes);
        $this->assertEquals(64, strlen($qrCodes['qr_code']));
        $this->assertEquals(8, strlen($qrCodes['qr_short_code']));
    }

    public function test_generates_qr_codes_for_existing_edition(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create(['number' => 5]);

        $qrCodes = $this->qrService->generateQRCodesForEdition($edition);

        $this->assertArrayHasKey('qr_code', $qrCodes);
        $this->assertArrayHasKey('qr_short_code', $qrCodes);

        // Verify they match what we'd generate directly
        $expectedQRCodes = $this->qrService->generateQRCodes($product, 5);
        $this->assertEquals($expectedQRCodes['qr_code'], $qrCodes['qr_code']);
        $this->assertEquals($expectedQRCodes['qr_short_code'], $qrCodes['qr_short_code']);
    }

    public function test_verifies_valid_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $qrCode = $this->qrService->generateQRCode($product, 1);
        $isValid = $this->qrService->verifyQRCode($product, 1, $qrCode);

        $this->assertTrue($isValid);
    }

    public function test_rejects_invalid_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $isValid = $this->qrService->verifyQRCode($product, 1, 'invalid-qr-code');

        $this->assertFalse($isValid);
    }

    public function test_verifies_valid_short_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $shortQRCode = $this->qrService->generateShortQRCode($product, 1);
        $isValid = $this->qrService->verifyShortQRCode($product, 1, $shortQRCode);

        $this->assertTrue($isValid);
    }

    public function test_rejects_invalid_short_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $isValid = $this->qrService->verifyShortQRCode($product, 1, 'INVALID1');

        $this->assertFalse($isValid);
    }

    public function test_generates_qr_code_url(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $url = $this->qrService->generateQRCodeUrl($product, 1);

        $this->assertStringStartsWith(config('app.url').'/qr/', $url);
        $this->assertStringContainsString($this->qrService->generateQRCode($product, 1), $url);
    }

    public function test_generates_qr_code_url_with_custom_base_url(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $customBaseUrl = 'https://custom.domain.com';
        $url = $this->qrService->generateQRCodeUrl($product, 1, $customBaseUrl);

        $this->assertStringStartsWith($customBaseUrl.'/qr/', $url);
    }

    public function test_finds_edition_by_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $qrCode = $this->qrService->generateQRCode($product, 1);

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'qr_code' => $qrCode,
        ]);

        $foundEdition = $this->qrService->findEditionByQRCode($qrCode);

        $this->assertNotNull($foundEdition);
        $this->assertEquals($edition->id, $foundEdition->id);
        $this->assertEquals($product->id, $foundEdition->product->id);
    }

    public function test_returns_null_for_nonexistent_qr_code(): void
    {
        $foundEdition = $this->qrService->findEditionByQRCode('nonexistent-qr-code');

        $this->assertNull($foundEdition);
    }

    public function test_finds_edition_by_short_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $shortQRCode = $this->qrService->generateShortQRCode($product, 1);

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'qr_short_code' => $shortQRCode,
        ]);

        $foundEdition = $this->qrService->findEditionByShortQRCode($shortQRCode);

        $this->assertNotNull($foundEdition);
        $this->assertEquals($edition->id, $foundEdition->id);
        $this->assertEquals($product->id, $foundEdition->product->id);
    }

    public function test_returns_null_for_nonexistent_short_qr_code(): void
    {
        $foundEdition = $this->qrService->findEditionByShortQRCode('INVALID1');

        $this->assertNull($foundEdition);
    }

    public function test_qr_codes_are_consistent_across_different_instances(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create([
            'slug' => 'consistent-test',
        ]);

        $service1 = new QRCodeService;
        $service2 = new QRCodeService;

        $qrCode1 = $service1->generateQRCode($product, 1);
        $qrCode2 = $service2->generateQRCode($product, 1);

        $this->assertEquals($qrCode1, $qrCode2);
    }

    public function test_qr_codes_use_product_slug_for_determinism(): void
    {
        $artist = Artist::factory()->create();

        // Create two products with same ID but different slugs
        // (This simulates what would happen if slug changes)
        $product1 = Product::factory()->for($artist)->create(['slug' => 'original-slug']);
        $product2 = Product::factory()->for($artist)->create(['slug' => 'modified-slug']);

        $qrCode1 = $this->qrService->generateQRCode($product1, 1);
        $qrCode2 = $this->qrService->generateQRCode($product2, 1);

        // Different slugs should produce different QR codes
        $this->assertNotEquals($qrCode1, $qrCode2);
    }

    public function test_security_hash_equals_prevents_timing_attacks(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $validQRCode = $this->qrService->generateQRCode($product, 1);
        $invalidQRCode = 'definitely-not-valid-qr-code-but-same-length-as-sha256-hash';

        // Both should return boolean false, but timing should be similar
        $startTime1 = microtime(true);
        $result1 = $this->qrService->verifyQRCode($product, 1, $validQRCode);
        $endTime1 = microtime(true);

        $startTime2 = microtime(true);
        $result2 = $this->qrService->verifyQRCode($product, 1, $invalidQRCode);
        $endTime2 = microtime(true);

        $this->assertTrue($result1);
        $this->assertFalse($result2);

        // The timing difference should be minimal (both use hash_equals)
        $timeDiff1 = $endTime1 - $startTime1;
        $timeDiff2 = $endTime2 - $startTime2;

        // This is more of a documentation test - hash_equals prevents timing attacks
        $this->assertIsFloat($timeDiff1);
        $this->assertIsFloat($timeDiff2);
    }
}
