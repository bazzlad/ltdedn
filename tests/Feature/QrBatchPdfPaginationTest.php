<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use App\Services\QrBatchPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class QrBatchPdfPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected QrBatchPdfService $service;

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(QrBatchPdfService::class);

        $this->admin = User::factory()->create(['role' => 'admin']);

        $artist = Artist::factory()->create();
        $this->product = Product::factory()->create(['artist_id' => $artist->id]);
    }

    public function test_generates_pdf_with_pagination_limit(): void
    {
        // Create more than 50 editions to test pagination with unique numbers
        for ($i = 1; $i <= 75; $i++) {
            ProductEdition::factory()->create([
                'product_id' => $this->product->id,
                'number' => $i,
            ]);
        }

        $request = new Request(['page' => 1, 'per_page' => 50]);

        $result = $this->service->generatePdf($request, $this->product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('pdf', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertEquals('product-'.$this->product->id.'-qrs.pdf', $result['filename']);
    }

    public function test_generates_pdf_with_page_number_in_filename(): void
    {
        // Create editions with unique numbers
        for ($i = 1; $i <= 75; $i++) {
            ProductEdition::factory()->create([
                'product_id' => $this->product->id,
                'number' => $i,
            ]);
        }

        $request = new Request(['page' => 2, 'per_page' => 50]);

        $result = $this->service->generatePdf($request, $this->product);

        $this->assertTrue($result['success']);
        $this->assertEquals('product-'.$this->product->id.'-qrs-page-2.pdf', $result['filename']);
    }

    public function test_handles_specific_edition_ids(): void
    {
        // Create editions with unique numbers
        $editions = collect();
        for ($i = 1; $i <= 10; $i++) {
            $editions->push(ProductEdition::factory()->create([
                'product_id' => $this->product->id,
                'number' => $i,
            ]));
        }

        $editionIds = $editions->take(3)->pluck('id')->toArray();
        $request = new Request;

        $result = $this->service->generatePdf($request, $this->product, $editionIds);

        $this->assertTrue($result['success']);
        $this->assertEquals('product-'.$this->product->id.'-qrs-selection.pdf', $result['filename']);
    }

    public function test_admin_can_access_qr_batch_pdf_endpoint(): void
    {
        $this->actingAs($this->admin);

        // Create some editions with unique numbers
        for ($i = 1; $i <= 5; $i++) {
            ProductEdition::factory()->create([
                'product_id' => $this->product->id,
                'number' => $i,
            ]);
        }

        $response = $this->get("/admin/products/{$this->product->id}/editions/qr-batch-pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_qr_batch_pdf_respects_pagination_params(): void
    {
        $this->actingAs($this->admin);

        // Create more editions than the limit with unique numbers
        for ($i = 1; $i <= 75; $i++) {
            ProductEdition::factory()->create([
                'product_id' => $this->product->id,
                'number' => $i,
            ]);
        }

        $response = $this->get("/admin/products/{$this->product->id}/editions/qr-batch-pdf?page=2&per_page=25");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
