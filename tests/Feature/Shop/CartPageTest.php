<?php

namespace Tests\Feature\Shop;

use App\Models\Artist;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_page_renders_empty_state_for_new_guest(): void
    {
        $response = $this->get(route('cart.show'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Cart')
            ->where('cart.item_count', 0)
            ->where('cart.subtotal', 0)
        );
    }

    public function test_cart_page_renders_user_cart_with_live_pricing(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);
        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 7500,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->forUser($user->id)->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 2,
            'unit_amount_snapshot' => 5000,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('cart.show'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Cart')
            ->where('cart.item_count', 2)
            ->where('cart.subtotal', 15000)
            ->has('cart.lines', 1)
            ->where('cart.lines.0.unit_amount', 7500)
            ->where('cart.lines.0.ok', true)
        );
    }

    public function test_shared_cart_summary_prop_exposes_item_count(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);
        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 5000,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->forUser($user->id)->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 3,
            'unit_amount_snapshot' => 5000,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('cartSummary.item_count', 3)
            ->where('cartSummary.subtotal', 15000)
        );
    }
}
