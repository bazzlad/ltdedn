<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductEditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_guest_cannot_access_editions(): void
    {
        $product = Product::factory()->for(Artist::factory())->create();

        $response = $this->get("/admin/products/{$product->id}/editions");
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_editions(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $product = Product::factory()->for(Artist::factory())->create();

        $response = $this->actingAs($user)->get("/admin/products/{$product->id}/editions");
        $response->assertStatus(403);
    }

    public function test_admin_can_view_editions_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        ProductEdition::factory()->for($product)->create(['number' => 1, 'status' => 'available']);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions");
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Editions/Index')
            ->has('product', fn (Assert $page) => $page
                ->where('id', $product->id)
                ->where('name', $product->name)
                ->has('artist', fn (Assert $page) => $page
                    ->where('id', $artist->id)
                    ->where('name', $artist->name)
                    ->etc()
                )
                ->etc()
            )
            ->has('editions')
            ->has('editions.data', 1)
            ->has('editions.data.0', fn (Assert $page) => $page
                ->where('number', 1)
                ->where('status', 'available')
                ->has('qr_code')
                ->etc()
            )
        );
    }

    public function test_artist_can_view_own_product_editions(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $product = Product::factory()->for($ownedArtist)->create();
        $edition = ProductEdition::factory()->for($product)->create(['number' => 1]);

        $response = $this->actingAs($artist)->get("/admin/products/{$product->id}/editions");
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Editions/Index')
            ->has('product', fn (Assert $page) => $page
                ->where('id', $product->id)
                ->where('name', $product->name)
                ->etc()
            )
            ->has('editions')
            ->has('editions.data', 1)
            ->has('editions.data.0', fn (Assert $page) => $page
                ->where('id', $edition->id)
                ->where('number', 1)
                ->has('qr_code')
                ->etc()
            )
        );
    }

    public function test_artist_cannot_view_unowned_product_editions(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();

        $response = $this->actingAs($artist)->get("/admin/products/{$product->id}/editions");
        $response->assertStatus(403);
    }

    public function test_admin_can_create_edition(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/create");
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Editions/Create')
            ->has('product', fn (Assert $page) => $page
                ->where('id', $product->id)
                ->where('name', $product->name)
                ->etc()
            )
            ->has('nextNumber')
            ->where('nextNumber', 1)
            ->has('users')
            ->has('statuses')
        );

        $editionData = [
            'number' => 1,
            'status' => 'available',
            'owner_id' => null,
        ];

        $response = $this->actingAs($admin)->post("/admin/products/{$product->id}/editions", $editionData);
        $response->assertRedirect("/admin/products/{$product->id}/editions");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('product_editions', [
            'product_id' => $product->id,
            'number' => 1,
            'status' => 'available',
        ]);
    }

    public function test_artist_can_create_edition_for_owned_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $product = Product::factory()->for($ownedArtist)->create();

        $editionData = [
            'number' => 1,
            'status' => 'available',
        ];

        $response = $this->actingAs($artist)->post("/admin/products/{$product->id}/editions", $editionData);
        $response->assertRedirect("/admin/products/{$product->id}/editions");

        $this->assertDatabaseHas('product_editions', [
            'product_id' => $product->id,
            'number' => 1,
        ]);
    }

    public function test_artist_cannot_create_edition_for_unowned_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();

        $editionData = [
            'number' => 1,
            'status' => 'available',
        ];

        $response = $this->actingAs($artist)->post("/admin/products/{$product->id}/editions", $editionData);
        $response->assertStatus(403);
    }

    public function test_admin_can_update_edition(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'status' => 'available',
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/editions/{$edition->id}/edit");
        $response->assertStatus(200);

        $updateData = [
            'number' => 1,
            'status' => 'sold',
            'owner_id' => null,
        ];

        $response = $this->actingAs($admin)->put("/admin/products/{$product->id}/editions/{$edition->id}", $updateData);
        $response->assertRedirect("/admin/products/{$product->id}/editions");

        $this->assertDatabaseHas('product_editions', [
            'id' => $edition->id,
            'status' => 'sold',
        ]);
    }

    public function test_artist_can_update_own_product_edition(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $product = Product::factory()->for($ownedArtist)->create();
        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 1,
            'status' => 'available',
        ]);

        $updateData = [
            'number' => 1,
            'status' => 'sold',
        ];

        $response = $this->actingAs($artist)->put("/admin/products/{$product->id}/editions/{$edition->id}", $updateData);
        $response->assertRedirect("/admin/products/{$product->id}/editions");

        $this->assertDatabaseHas('product_editions', [
            'id' => $edition->id,
            'status' => 'sold',
        ]);
    }

    public function test_artist_cannot_update_unowned_product_edition(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();
        $edition = ProductEdition::factory()->for($product)->create();

        $response = $this->actingAs($artist)->put("/admin/products/{$product->id}/editions/{$edition->id}", [
            'number' => $edition->number,
            'status' => 'sold',
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_delete_edition(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();
        $edition = ProductEdition::factory()->for($product)->create(['number' => 1]);

        $response = $this->actingAs($admin)->delete("/admin/products/{$product->id}/editions/{$edition->id}");
        $response->assertRedirect("/admin/products/{$product->id}/editions");

        $this->assertSoftDeleted('product_editions', ['id' => $edition->id]);
    }

    public function test_artist_can_delete_own_product_edition(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $product = Product::factory()->for($ownedArtist)->create();
        $edition = ProductEdition::factory()->for($product)->create(['number' => 1]);

        $response = $this->actingAs($artist)->delete("/admin/products/{$product->id}/editions/{$edition->id}");
        $response->assertRedirect("/admin/products/{$product->id}/editions");

        $this->assertSoftDeleted('product_editions', ['id' => $edition->id]);
    }

    public function test_artist_cannot_delete_unowned_product_edition(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();
        $edition = ProductEdition::factory()->for($product)->create();

        $response = $this->actingAs($artist)->delete("/admin/products/{$product->id}/editions/{$edition->id}");
        $response->assertStatus(403);

        $this->assertDatabaseHas('product_editions', ['id' => $edition->id]);
    }

    public function test_edition_validation_rules(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $response = $this->actingAs($admin)->post("/admin/products/{$product->id}/editions", []);
        $response->assertSessionHasErrors(['number', 'status']);

        $response = $this->actingAs($admin)->post("/admin/products/{$product->id}/editions", [
            'number' => 0, // Invalid number
            'status' => 'invalid_status',
        ]);
        $response->assertSessionHasErrors(['number', 'status']);
    }

    public function test_unique_edition_number_per_product(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        ProductEdition::factory()->for($product)->create(['number' => 1]);

        $response = $this->actingAs($admin)->post("/admin/products/{$product->id}/editions", [
            'number' => 1,
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors(['number']);
        $this->assertEquals(1, ProductEdition::where('product_id', $product->id)->count());
    }

    public function test_edition_number_can_be_same_across_different_products(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product1 = Product::factory()->for($artist)->create();
        $product2 = Product::factory()->for($artist)->create();

        ProductEdition::factory()->for($product1)->create(['number' => 1]);

        $response = $this->actingAs($admin)->post("/admin/products/{$product2->id}/editions", [
            'number' => 1,
            'status' => 'available',
        ]);
        $response->assertRedirect("/admin/products/{$product2->id}/editions");

        $this->assertDatabaseHas('product_editions', [
            'product_id' => $product2->id,
            'number' => 1,
        ]);
    }

    public function test_edition_automatically_generates_qr_codes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $response = $this->actingAs($admin)->post("/admin/products/{$product->id}/editions", [
            'number' => 1,
            'status' => 'available',
        ]);

        $edition = ProductEdition::where('product_id', $product->id)->first();
        $this->assertNotNull($edition->qr_code);
        $this->assertEquals(64, strlen($edition->qr_code));
    }

    public function test_edition_qr_codes_are_deterministic_using_service(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        // Create an edition
        $edition = ProductEdition::factory()->for($product)->create([
            'number' => 5,
            'status' => 'available',
        ]);

        $qrService = app(\App\Services\QRCodeService::class);
        $expectedQRCode = $qrService->generateQRCode($product, 5);

        $this->assertEquals($expectedQRCode, $edition->qr_code);
    }
}
