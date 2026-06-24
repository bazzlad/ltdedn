<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
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
        $response->assertDownload("product-{$product->id}-test-product-editions.csv");

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
}
