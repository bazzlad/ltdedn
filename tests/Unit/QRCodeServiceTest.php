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

    public function test_generates_random_qr_code_tokens(): void
    {
        $qrCode1 = $this->qrService->generateQRCode();
        $qrCode2 = $this->qrService->generateQRCode();

        $this->assertIsString($qrCode1);
        $this->assertIsString($qrCode2);
        $this->assertEquals(64, strlen($qrCode1));
        $this->assertEquals(64, strlen($qrCode2));
        $this->assertNotEquals($qrCode1, $qrCode2);
    }

    public function test_generate_qr_code_for_edition_returns_existing_value_when_present(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 5,
            'qr_code' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);

        $qrCode = $this->qrService->generateQRCodeForEdition($edition);

        $this->assertEquals($edition->qr_code, $qrCode);
    }

    public function test_verify_qr_code_checks_persisted_edition_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 7,
            'qr_code' => $this->qrService->generateQRCode(),
        ]);

        $this->assertTrue($this->qrService->verifyQRCode($product, 7, $edition->qr_code));
        $this->assertFalse($this->qrService->verifyQRCode($product, 7, 'invalid-code'));
    }

    public function test_generates_qr_code_url_for_existing_edition(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 2,
            'qr_code' => $this->qrService->generateQRCode(),
        ]);

        $url = $this->qrService->generateQRCodeUrl($product, 2);

        $this->assertStringStartsWith(config('app.url').'/qr/', $url);
        $this->assertStringEndsWith($edition->qr_code, $url);
    }

    public function test_finds_edition_by_qr_code(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $qrCode = $this->qrService->generateQRCode();

        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'qr_code' => $qrCode,
        ]);

        $foundEdition = $this->qrService->findEditionByQRCode($qrCode);

        $this->assertNotNull($foundEdition);
        $this->assertEquals($edition->id, $foundEdition->id);
        $this->assertEquals($product->id, $foundEdition->product->id);
    }
}
