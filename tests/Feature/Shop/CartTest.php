<?php

namespace Tests\Feature\Shop;

use App\Models\Artist;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function sellableProductWithSku(array $skuOverrides = []): array
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create(array_merge([
            'product_id' => $product->id,
            'price_amount' => 5000,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
            'is_active' => true,
        ], $skuOverrides));

        return [$product, $sku];
    }

    public function test_guest_can_add_item_and_cookie_persists_cart(): void
    {
        [$product, $sku] = $this->sellableProductWithSku();

        $response = $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 2,
            'unit_amount_snapshot' => 5000,
        ]);
    }

    public function test_adding_same_sku_increments_quantity_not_creates_new_line(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$product, $sku] = $this->sellableProductWithSku();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
        ]);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 3,
        ]);
    }

    public function test_add_item_clamps_quantity_to_available_stock(): void
    {
        [$product, $sku] = $this->sellableProductWithSku(['stock_on_hand' => 2]);

        $response = $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cart_items', [
            'product_sku_id' => $sku->id,
            'quantity' => 2,
        ]);
    }

    public function test_add_item_rejected_when_sku_out_of_stock(): void
    {
        [$product, $sku] = $this->sellableProductWithSku([
            'stock_on_hand' => 1,
            'stock_reserved' => 1,
        ]);

        $response = $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_add_item_rejected_when_product_unpurchasable(): void
    {
        [$product, $sku] = $this->sellableProductWithSku();
        $product->update(['is_sellable' => false]);

        $response = $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_update_quantity_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$product, $sku] = $this->sellableProductWithSku();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
        ]);

        $item = CartItem::query()->firstOrFail();

        $this->patch(route('cart.items.update', $item), ['quantity' => 3])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 3]);
    }

    public function test_update_to_zero_deletes_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$product, $sku] = $this->sellableProductWithSku();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
        ]);

        $item = CartItem::query()->firstOrFail();

        $this->patch(route('cart.items.update', $item), ['quantity' => 0]);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_destroy_item_removes_only_that_line(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$productA, $skuA] = $this->sellableProductWithSku();
        [$productB, $skuB] = $this->sellableProductWithSku();

        $this->post(route('cart.items.store'), [
            'product_id' => $productA->id,
            'product_sku_id' => $skuA->id,
            'quantity' => 1,
        ]);

        $this->post(route('cart.items.store'), [
            'product_id' => $productB->id,
            'product_sku_id' => $skuB->id,
            'quantity' => 1,
        ]);

        $itemA = CartItem::query()->where('product_sku_id', $skuA->id)->firstOrFail();

        $this->delete(route('cart.items.destroy', $itemA))->assertRedirect();

        $this->assertDatabaseMissing('cart_items', ['id' => $itemA->id]);
        $this->assertDatabaseHas('cart_items', ['product_sku_id' => $skuB->id]);
    }

    public function test_cannot_modify_another_carts_item(): void
    {
        [$product, $sku] = $this->sellableProductWithSku();
        $otherCart = Cart::factory()->create(['session_token' => 'other-token']);
        $foreignItem = CartItem::factory()->create([
            'cart_id' => $otherCart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
            'unit_amount_snapshot' => 5000,
        ]);

        $response = $this->patch(route('cart.items.update', $foreignItem), ['quantity' => 5]);
        $response->assertForbidden();

        $this->assertDatabaseHas('cart_items', ['id' => $foreignItem->id, 'quantity' => 1]);
    }

    public function test_guest_cart_merges_into_user_cart(): void
    {
        [$product, $sku] = $this->sellableProductWithSku(['stock_on_hand' => 10]);

        $guest = Cart::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $guest->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 2,
            'unit_amount_snapshot' => 5000,
        ]);

        $user = User::factory()->create();
        $userCart = Cart::factory()->forUser($user->id)->create();
        CartItem::factory()->create([
            'cart_id' => $userCart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 3,
            'unit_amount_snapshot' => 5000,
        ]);

        /** @var CartService $service */
        $service = app(CartService::class);
        $service->mergeGuestIntoUser($guest, $userCart);

        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_sku_id' => $sku->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseMissing('carts', ['id' => $guest->id]);
    }

    public function test_merge_clamps_combined_quantity_to_stock(): void
    {
        [$product, $sku] = $this->sellableProductWithSku(['stock_on_hand' => 4]);

        $guest = Cart::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $guest->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 3,
            'unit_amount_snapshot' => 5000,
        ]);

        $user = User::factory()->create();
        $userCart = Cart::factory()->forUser($user->id)->create();
        CartItem::factory()->create([
            'cart_id' => $userCart->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 3,
            'unit_amount_snapshot' => 5000,
        ]);

        /** @var CartService $service */
        $service = app(CartService::class);
        $service->mergeGuestIntoUser($guest, $userCart);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_sku_id' => $sku->id,
            'quantity' => 4,
        ]);
    }

    public function test_merge_is_noop_when_guest_has_no_items(): void
    {
        $guest = Cart::factory()->create();
        $user = User::factory()->create();
        $userCart = Cart::factory()->forUser($user->id)->create();

        /** @var CartService $service */
        $service = app(CartService::class);
        $service->mergeGuestIntoUser($guest, $userCart);

        $this->assertDatabaseMissing('carts', ['id' => $guest->id]);
        $this->assertDatabaseHas('carts', ['id' => $userCart->id]);
    }

    public function test_logged_in_user_gets_dedicated_cart_from_resolve(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        [$product, $sku] = $this->sellableProductWithSku();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    }

    public function test_stock_mutations_do_not_occur_on_add_to_cart(): void
    {
        [$product, $sku] = $this->sellableProductWithSku();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('product_skus', [
            'id' => $sku->id,
            'stock_reserved' => 0,
            'stock_on_hand' => 5,
        ]);
    }

    public function test_cart_service_resolve_creates_fresh_cart_per_user(): void
    {
        /** @var CartService $service */
        $service = app(CartService::class);
        $user = User::factory()->create();

        $request = \Illuminate\Http\Request::create('/cart');
        $request->setUserResolver(fn () => $user);

        $cart = $service->resolveForRequest($request);
        $cart2 = $service->resolveForRequest($request);

        $this->assertSame($cart->id, $cart2->id);
        $this->assertSame($user->id, $cart->user_id);
    }
}
