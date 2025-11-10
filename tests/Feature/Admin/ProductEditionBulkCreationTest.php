<?php

namespace Tests\Feature\Admin;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditionBulkCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_create_editions(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $this->actingAs($admin);

        $response = $this->post("/admin/products/{$product->id}/editions/bulk", [
            'start_number' => 1,
            'quantity' => 5,
            'status' => 'available',
        ]);

        $response->assertRedirect("/admin/products/{$product->id}/editions");
        $response->assertSessionHas('success', '5 editions created successfully (#1 - #5).');

        $this->assertDatabaseCount('product_editions', 5);

        // Check that editions were created with correct numbers
        for ($i = 1; $i <= 5; $i++) {
            $this->assertDatabaseHas('product_editions', [
                'product_id' => $product->id,
                'number' => $i,
                'status' => 'available',
            ]);
        }
    }

    public function test_bulk_creation_prevents_duplicate_numbers(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        // Create an existing edition
        ProductEdition::factory()->for($product)->create(['number' => 3]);

        $this->actingAs($admin);

        $response = $this->post("/admin/products/{$product->id}/editions/bulk", [
            'start_number' => 1,
            'quantity' => 5,
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors(['start_number']);
        $this->assertStringContainsString('Edition numbers 3 already exist', session('errors')->first('start_number'));
    }

    public function test_bulk_creation_with_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $this->actingAs($admin);

        $response = $this->post("/admin/products/{$product->id}/editions/bulk", [
            'start_number' => 1,
            'quantity' => 3,
            'status' => 'sold',
            'owner_id' => $owner->id,
        ]);

        $response->assertRedirect("/admin/products/{$product->id}/editions");

        // Check that all editions have the correct owner
        $this->assertDatabaseCount('product_editions', 3);
        for ($i = 1; $i <= 3; $i++) {
            $this->assertDatabaseHas('product_editions', [
                'product_id' => $product->id,
                'number' => $i,
                'status' => 'sold',
                'owner_id' => $owner->id,
            ]);
        }
    }

    public function test_artist_can_bulk_create_editions_for_own_products(): void
    {
        $user = User::factory()->artist()->create();
        $artist = Artist::factory()->create(['owner_id' => $user->id]); // User owns the artist

        $product = Product::factory()->for($artist)->create();

        $this->actingAs($user);

        $response = $this->post("/admin/products/{$product->id}/editions/bulk", [
            'start_number' => 1,
            'quantity' => 2,
            'status' => 'available',
        ]);

        $response->assertRedirect("/admin/products/{$product->id}/editions");
        $this->assertDatabaseCount('product_editions', 2);
    }

    public function test_bulk_creation_validates_quantity_limit(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $this->actingAs($admin);

        $response = $this->post("/admin/products/{$product->id}/editions/bulk", [
            'start_number' => 1,
            'quantity' => 1001, // Exceeds limit
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors(['quantity']);
    }
}
