<?php

namespace Tests\Unit;

use App\Models\Artist;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductSku;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_snapshot_recomputes_from_live_price_not_snapshot(): void
    {
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
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 2,
            'unit_amount_snapshot' => 3000,
        ]);

        $sku->update(['price_amount' => 7500]);

        /** @var CartService $service */
        $service = app(CartService::class);
        $snapshot = $service->pricingSnapshot($cart->fresh());

        $this->assertSame(15000, $snapshot['subtotal']);
        $this->assertSame(2, $snapshot['item_count']);
        $this->assertSame(7500, $snapshot['lines'][0]['unit_amount']);
        $this->assertTrue($snapshot['lines'][0]['ok']);
    }

    public function test_pricing_snapshot_flags_unpurchasable_line(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => false,
            'sale_status' => 'active',
        ]);
        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 5000,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
            'unit_amount_snapshot' => 5000,
        ]);

        /** @var CartService $service */
        $service = app(CartService::class);
        $snapshot = $service->pricingSnapshot($cart->fresh());

        $this->assertFalse($snapshot['lines'][0]['ok']);
        $this->assertSame(0, $snapshot['subtotal']);
        $this->assertSame(0, $snapshot['item_count']);
    }

    public function test_pricing_snapshot_flags_line_when_qty_exceeds_available(): void
    {
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
            'stock_on_hand' => 2,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 5,
            'unit_amount_snapshot' => 5000,
        ]);

        /** @var CartService $service */
        $service = app(CartService::class);
        $snapshot = $service->pricingSnapshot($cart->fresh());

        $this->assertFalse($snapshot['lines'][0]['ok']);
        $this->assertStringContainsString('in stock', (string) $snapshot['lines'][0]['error']);
    }
}
