<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductEditionStatus;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditionCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_product_editions_csv(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create(['name' => 'Test Product']);

        ProductEdition::factory()->for($product)->create([
            'number' => 2,
            'qr_code' => 'second-code',
        ]);

        ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'qr_code' => 'first-code',
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/csv");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload("product-{$product->id}-test-product-qr-codes.csv");

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertSame(['QR', 'LOGO', 'EDN', 'TOTAL'], $rows[0]);
        $this->assertSame([url('/qr/first-code'), '', '1', '2'], $rows[1]);
        $this->assertSame([url('/qr/second-code'), '', '2', '2'], $rows[2]);
    }

    public function test_csv_can_include_logo_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'qr_code' => 'first-code',
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/csv?logo=shirt-logo.png");

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertSame([url('/qr/first-code'), 'shirt-logo.png', '1', '1'], $rows[1]);
    }

    public function test_csv_pads_edition_numbers_to_total_digit_width(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        foreach (range(1, 100) as $number) {
            ProductEdition::factory()->for($product)->create([
                'number' => $number,
                'qr_code' => "code-{$number}",
            ]);
        }

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/csv");

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertSame([url('/qr/code-1'), '', '001', '100'], $rows[1]);
        $this->assertSame([url('/qr/code-2'), '', '002', '100'], $rows[2]);
        $this->assertSame([url('/qr/code-100'), '', '100', '100'], $rows[100]);
    }

    public function test_artist_can_download_csv_for_owned_product(): void
    {
        $artistUser = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistUser->id]);
        $product = Product::factory()->for($artist)->create();

        ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'qr_code' => 'owned-code',
        ]);

        $response = $this->actingAs($artistUser)->get("/admin/products/{$product->id}/editions/csv");

        $response->assertOk();
        $this->assertStringContainsString(url('/qr/owned-code'), $response->streamedContent());
    }

    public function test_artist_cannot_download_csv_for_unowned_product(): void
    {
        $artistUser = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();

        $response = $this->actingAs($artistUser)->get("/admin/products/{$product->id}/editions/csv");

        $response->assertForbidden();
    }

    public function test_admin_can_download_product_edition_sku_csv(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create(['name' => 'Test Product']);
        $sku = ProductSku::factory()->for($product)->create(['sku_code' => 'PRINT-001']);

        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => $sku->id,
            'number' => 2,
            'status' => ProductEditionStatus::Sold,
        ]);

        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => $sku->id,
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/sku-csv");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload("product-{$product->id}-test-product-skus.csv");

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertSame(['SKU', 'EDN', 'STATUS', 'SOLD'], $rows[0]);
        $this->assertSame(['PRINT-001', '1', 'available', 'No'], $rows[1]);
        $this->assertSame(['PRINT-001', '2', 'sold', 'Yes'], $rows[2]);
    }

    public function test_sku_csv_uses_single_product_sku_for_unassigned_editions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        ProductSku::factory()->for($product)->create(['sku_code' => 'DEFAULT-001']);
        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => null,
            'number' => 1,
            'status' => ProductEditionStatus::Redeemed,
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/sku-csv");

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertSame(['DEFAULT-001', '1', 'redeemed', 'Yes'], $rows[1]);
    }

    public function test_sku_csv_creates_default_sku_when_product_has_none(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create(['name' => 'I Am Turning']);

        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => null,
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/sku-csv");

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertSame(['LTD-'.$product->id.'-I-AM-TURNING', '1', 'available', 'No'], $rows[1]);
        $this->assertDatabaseHas('product_skus', [
            'product_id' => $product->id,
            'sku_code' => 'LTD-'.$product->id.'-I-AM-TURNING',
            'stock_on_hand' => 1,
        ]);
    }

    public function test_sku_csv_sync_preserves_reserved_stock(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create(['is_limited' => true]);
        $sku = ProductSku::factory()->for($product)->create([
            'sku_code' => 'RESERVED-001',
            'stock_on_hand' => 10,
            'stock_reserved' => 2,
        ]);

        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => $sku->id,
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/sku-csv")->assertOk();

        $sku->refresh();

        $this->assertSame(2, $sku->stock_reserved);
        $this->assertSame(3, $sku->stock_on_hand);
        $this->assertSame(1, $sku->stock_available);
    }

    public function test_artist_can_download_sku_csv_for_owned_product(): void
    {
        $artistUser = User::factory()->create(['role' => UserRole::Artist]);
        $artist = Artist::factory()->create(['owner_id' => $artistUser->id]);
        $product = Product::factory()->for($artist)->create();

        ProductSku::factory()->for($product)->create(['sku_code' => 'OWNED-001']);
        ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $response = $this->actingAs($artistUser)->get("/admin/products/{$product->id}/editions/sku-csv");

        $response->assertOk();
        $this->assertStringContainsString('OWNED-001', $response->streamedContent());
    }

    public function test_artist_cannot_download_sku_csv_for_unowned_product(): void
    {
        $artistUser = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();

        $response = $this->actingAs($artistUser)->get("/admin/products/{$product->id}/editions/sku-csv");

        $response->assertForbidden();
    }
}
