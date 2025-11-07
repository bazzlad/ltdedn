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

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_guest_cannot_access_products(): void
    {
        $response = $this->get('/admin/products');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_products(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->get('/admin/products');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_products_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $response = $this->actingAs($admin)->get('/admin/products');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->has('products')
            ->has('products.data')
            ->has('products.links')
            ->etc()
        );
    }

    public function test_admin_can_view_products_index_with_edition_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        // Create some editions with different statuses
        ProductEdition::factory()->for($product)->create(['status' => 'available', 'number' => 1]);
        ProductEdition::factory()->for($product)->create(['status' => 'redeemed', 'number' => 2]);
        ProductEdition::factory()->for($product)->create(['status' => 'sold', 'number' => 3]);

        $response = $this->actingAs($admin)->get('/admin/products');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->has('products.data')
            ->where('products.data', fn ($products) => collect($products)->contains(fn ($p) => $p['id'] === $product->id &&
                    $p['editions_count'] === 3 &&
                    count($p['editions']) === 3
            )
            )
        );
    }

    public function test_artist_can_view_own_products_only(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $otherArtist = Artist::factory()->create();

        $ownProduct = Product::factory()->for($ownedArtist)->create();
        $otherProduct = Product::factory()->for($otherArtist)->create();

        $response = $this->actingAs($artist)->get('/admin/products');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->has('products')
            ->has('products.data', 1)
            ->has('products.data.0', fn (Assert $page) => $page
                ->where('id', $ownProduct->id)
                ->where('name', $ownProduct->name)
                ->has('artist', fn (Assert $page) => $page
                    ->where('id', $ownedArtist->id)
                    ->where('name', $ownedArtist->name)
                    ->etc()
                )
                ->etc()
            )
            ->missing('products.data.1')
        );
    }

    public function test_admin_can_search_products(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create(['name' => 'Unique Test Artist']);

        $matchingProduct = Product::factory()->for($artist)->create([
            'name' => 'Unique Banana Split Album',
            'description' => 'A great album about bananas',
        ]);

        $nonMatchingProduct = Product::factory()->for($artist)->create([
            'name' => 'Unique Rock Album',
            'description' => 'Heavy metal music',
        ]);

        // Test search by product name - should find only the banana product
        $response = $this->actingAs($admin)->get('/admin/products?search=Unique Banana');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->has('products')
            ->has('products.data', 1)
            ->has('products.data.0', fn (Assert $page) => $page
                ->where('id', $matchingProduct->id)
                ->where('name', $matchingProduct->name)
                ->etc()
            )
            ->has('filters', fn (Assert $page) => $page
                ->where('search', 'Unique Banana')
                ->etc()
            )
        );

        // Test search by artist name - should find both products from this artist
        $response = $this->actingAs($admin)->get('/admin/products?search=Unique Test Artist');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->has('products')
            ->has('products.data', 2) // Both products should match
            ->has('filters', fn (Assert $page) => $page
                ->where('search', 'Unique Test Artist')
                ->etc()
            )
        );

        // Test search with no matches
        $response = $this->actingAs($admin)->get('/admin/products?search=NonExistentSearchTerm');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->has('products')
            ->has('products.data', 0) // No matches
            ->has('filters', fn (Assert $page) => $page
                ->where('search', 'NonExistentSearchTerm')
                ->etc()
            )
        );
    }

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/products/create');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Create')
            ->has('artists')
            ->etc()
        );

        $productData = [
            'artist_id' => $artist->id,
            'name' => 'Test Product',
            'description' => 'Test description',
            'sell_through_ltdedn' => true,
            'is_limited' => true,
            'edition_size' => 100,
            'base_price' => 29.99,
            'is_public' => false,
        ];

        $response = $this->actingAs($admin)->post('/admin/products', $productData);

        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'artist_id' => $artist->id,
        ]);
    }

    public function test_artist_can_create_product_for_owned_artist(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);

        $productData = [
            'artist_id' => $ownedArtist->id,
            'name' => 'Artist Product',
            'description' => 'Artist description',
            'sell_through_ltdedn' => false,
            'is_limited' => false,
            'is_public' => true,
        ];

        $response = $this->actingAs($artist)->post('/admin/products', $productData);
        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'name' => 'Artist Product',
            'artist_id' => $ownedArtist->id,
        ]);
    }

    public function test_artist_cannot_create_product_for_unowned_artist(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();

        $productData = [
            'artist_id' => $otherArtist->id,
            'name' => 'Unauthorized Product',
        ];

        $response = $this->actingAs($artist)->post('/admin/products', $productData);
        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_product(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Show')
            ->has('product', fn (Assert $page) => $page
                ->where('id', $product->id)
                ->where('name', $product->name)
                ->where('slug', $product->slug)
                ->has('artist', fn (Assert $page) => $page
                    ->where('id', $artist->id)
                    ->where('name', $artist->name)
                    ->etc()
                )
                ->etc()
            )
            ->has('editions')
        );
    }

    public function test_artist_can_view_own_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $product = Product::factory()->for($ownedArtist)->create();

        $response = $this->actingAs($artist)->get("/admin/products/{$product->id}");
        $response->assertStatus(200);
    }

    public function test_artist_cannot_view_unowned_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();

        $response = $this->actingAs($artist)->get("/admin/products/{$product->id}");
        $response->assertStatus(403);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create(['name' => 'Original Name']);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/edit");
        $response->assertStatus(200);

        $updateData = [
            'artist_id' => $artist->id,
            'name' => 'Updated Name',
            'description' => $product->description,
            'sell_through_ltdedn' => $product->sell_through_ltdedn,
            'is_limited' => $product->is_limited,
            'is_public' => $product->is_public,
        ];

        $response = $this->actingAs($admin)->put("/admin/products/{$product->id}", $updateData);
        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_artist_can_update_own_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $product = Product::factory()->for($ownedArtist)->create(['name' => 'Original Name']);

        $updateData = [
            'artist_id' => $ownedArtist->id,
            'name' => 'Updated by Artist',
            'description' => $product->description,
            'sell_through_ltdedn' => $product->sell_through_ltdedn,
            'is_limited' => $product->is_limited,
            'is_public' => $product->is_public,
        ];

        $response = $this->actingAs($artist)->put("/admin/products/{$product->id}", $updateData);
        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated by Artist',
        ]);
    }

    public function test_artist_cannot_update_unowned_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();

        $response = $this->actingAs($artist)->put("/admin/products/{$product->id}", [
            'artist_id' => $otherArtist->id,
            'name' => 'Unauthorized Update',
            'description' => $product->description,
            'sell_through_ltdedn' => $product->sell_through_ltdedn,
            'is_limited' => $product->is_limited,
            'is_public' => $product->is_public,
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create();

        $response = $this->actingAs($admin)->delete("/admin/products/{$product->id}");
        $response->assertRedirect('/admin/products');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_artist_can_delete_own_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artist->id]);
        $product = Product::factory()->for($ownedArtist)->create();

        $response = $this->actingAs($artist)->delete("/admin/products/{$product->id}");
        $response->assertRedirect('/admin/products');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_artist_cannot_delete_unowned_product(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $otherArtist = Artist::factory()->create();
        $product = Product::factory()->for($otherArtist)->create();

        $response = $this->actingAs($artist)->delete("/admin/products/{$product->id}");
        $response->assertStatus(403);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_validation_rules(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->post('/admin/products', []);
        $response->assertSessionHasErrors(['artist_id', 'name']);

        $response = $this->actingAs($admin)->post('/admin/products', [
            'artist_id' => 999, // Non-existent artist
            'name' => '',
        ]);
        $response->assertSessionHasErrors(['artist_id', 'name']);
    }
}
