<?php

namespace Tests\Feature\Shop;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ArtistPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_page_loads_with_bio_and_hero_for_guest(): void
    {
        $artist = Artist::factory()->create([
            'name' => 'Myartist',
            'slug' => 'myartist',
            'bio' => 'Multi-line bio.',
            'hero_image' => 'artists/hero.jpg',
        ]);

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => null,
            'status' => 'available',
        ]);

        $response = $this->get(route('shop.artist', ['artistSlug' => $artist->slug]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Shop/Artist')
            ->where('artist.name', 'Myartist')
            ->where('artist.bio', 'Multi-line bio.')
            ->where('artist.hero_image', '/storage/artists/hero.jpg')
            ->has('products', 1)
            ->where('products.0.name', $product->name)
        );
    }

    public function test_artist_page_lists_only_that_artists_products(): void
    {
        $artistA = Artist::factory()->create(['slug' => 'artist-a']);
        $artistB = Artist::factory()->create(['slug' => 'artist-b']);

        foreach ([$artistA, $artistB] as $artist) {
            $product = Product::factory()->create([
                'artist_id' => $artist->id,
                'is_public' => true,
                'sell_through_ltdedn' => true,
                'is_sellable' => true,
                'sale_status' => 'active',
            ]);
            ProductEdition::factory()->create([
                'product_id' => $product->id,
                'product_sku_id' => null,
                'status' => 'available',
            ]);
        }

        $this->get(route('shop.artist', ['artistSlug' => 'artist-a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Shop/Artist')
                ->has('products', 1)
                ->where('products.0.name', Product::where('artist_id', $artistA->id)->first()->name)
            );
    }

    public function test_artist_page_hides_non_public_products_from_guests_but_shows_them_to_authenticated_users(): void
    {
        $artist = Artist::factory()->create(['slug' => 'mixed']);

        $publicProduct = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'name' => 'Public piece',
        ]);
        ProductEdition::factory()->create(['product_id' => $publicProduct->id, 'product_sku_id' => null, 'status' => 'available']);

        $privateProduct = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => false,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'name' => 'Private piece',
        ]);
        ProductEdition::factory()->create(['product_id' => $privateProduct->id, 'product_sku_id' => null, 'status' => 'available']);

        $this->get(route('shop.artist', ['artistSlug' => 'mixed']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 1));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('shop.artist', ['artistSlug' => 'mixed']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 2));
    }

    public function test_artist_page_excludes_sold_out_products(): void
    {
        $artist = Artist::factory()->create(['slug' => 'soldout']);

        $soldOut = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);
        ProductEdition::factory()->create(['product_id' => $soldOut->id, 'product_sku_id' => null, 'status' => 'sold']);

        $this->get(route('shop.artist', ['artistSlug' => 'soldout']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 0));
    }

    public function test_artist_page_shows_sku_products_without_standard_editions(): void
    {
        $artist = Artist::factory()->create(['slug' => 'skus-only']);

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);
        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $this->get(route('shop.artist', ['artistSlug' => 'skus-only']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 1));
    }

    public function test_unknown_artist_slug_returns_404(): void
    {
        $this->get(route('shop.artist', ['artistSlug' => 'nobody-here']))
            ->assertNotFound();
    }

    public function test_shop_index_product_cards_expose_artist_name_and_url(): void
    {
        $artist = Artist::factory()->create(['name' => 'Zara', 'slug' => 'zara']);
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);
        ProductEdition::factory()->create(['product_id' => $product->id, 'product_sku_id' => null, 'status' => 'available']);

        $this->get(route('shop.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Shop')
                ->where('products.0.artist_name', 'Zara')
                ->where('products.0.artist_url', route('shop.artist', ['artistSlug' => 'zara']))
            );
    }
}
