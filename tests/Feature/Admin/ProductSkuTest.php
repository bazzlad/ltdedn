<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_sku(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->create(['artist_id' => $artist->id]);

        $create = $this->actingAs($admin)->post('/admin/products/'.$product->id.'/skus', [
            'sku_code' => 'TEE-BLK-XL',
            'price_amount' => 2999,
            'currency' => 'gbp',
            'stock_on_hand' => 3,
            'is_active' => true,
            'attributes' => ['size' => 'XL'],
        ]);

        $create->assertRedirect('/admin/products/'.$product->id.'/skus');

        $sku = ProductSku::where('sku_code', 'TEE-BLK-XL')->firstOrFail();
        $this->assertSame('gbp', $sku->currency);

        $this->assertDatabaseHas('sku_stock_adjustments', [
            'product_sku_id' => $sku->id,
            'delta_on_hand' => 3,
            'before_on_hand' => 0,
            'after_on_hand' => 3,
            'reason' => 'initial_stock',
            'source' => 'admin',
        ]);

        $update = $this->actingAs($admin)->put('/admin/products/'.$product->id.'/skus/'.$sku->id, [
            'sku_code' => 'TEE-BLK-XL',
            'price_amount' => 3499,
            'currency' => 'gbp',
            'stock_on_hand' => 5,
            'is_active' => false,
            'attributes' => ['size' => 'XL', 'color' => 'Black'],
        ]);

        $update->assertRedirect('/admin/products/'.$product->id.'/skus');

        $this->assertDatabaseHas('product_skus', [
            'id' => $sku->id,
            'price_amount' => 3499,
            'stock_on_hand' => 5,
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('sku_stock_adjustments', [
            'product_sku_id' => $sku->id,
            'delta_on_hand' => 2,
            'before_on_hand' => 3,
            'after_on_hand' => 5,
            'reason' => 'manual_stock_update',
            'source' => 'admin',
        ]);

        // SKU with stock adjustment history cannot be deleted (restrict FK)
        $delete = $this->actingAs($admin)->delete('/admin/products/'.$product->id.'/skus/'.$sku->id);
        $delete->assertSessionHasErrors('sku');

        $this->assertDatabaseHas('product_skus', ['id' => $sku->id]);
    }

    public function test_admin_can_delete_sku_without_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->create(['artist_id' => $artist->id]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'stock_on_hand' => 0,
            'stock_reserved' => 0,
        ]);

        $delete = $this->actingAs($admin)->delete('/admin/products/'.$product->id.'/skus/'.$sku->id);
        $delete->assertRedirect('/admin/products/'.$product->id.'/skus');

        $this->assertDatabaseMissing('product_skus', ['id' => $sku->id]);
    }

    public function test_artist_can_manage_own_product_skus_but_not_others(): void
    {
        $artistUser = User::factory()->create(['role' => UserRole::Artist]);
        $ownedArtist = Artist::factory()->create(['owner_id' => $artistUser->id]);
        $otherArtist = Artist::factory()->create();

        $ownedProduct = Product::factory()->create(['artist_id' => $ownedArtist->id]);
        $otherProduct = Product::factory()->create(['artist_id' => $otherArtist->id]);

        $ok = $this->actingAs($artistUser)->post('/admin/products/'.$ownedProduct->id.'/skus', [
            'sku_code' => 'OWN-1',
            'price_amount' => 1000,
            'currency' => 'gbp',
            'stock_on_hand' => 1,
        ]);
        $ok->assertRedirect('/admin/products/'.$ownedProduct->id.'/skus');

        $forbidden = $this->actingAs($artistUser)->post('/admin/products/'.$otherProduct->id.'/skus', [
            'sku_code' => 'OTHER-1',
            'price_amount' => 1000,
            'currency' => 'gbp',
            'stock_on_hand' => 1,
        ]);
        $forbidden->assertStatus(403);
    }

    public function test_admin_can_update_product_sellability_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_sellable' => false,
            'sale_status' => 'draft',
            'currency' => 'gbp',
        ]);

        // Product needs at least one edition to be sellable
        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'status' => 'available',
        ]);

        $response = $this->actingAs($admin)->put('/admin/products/'.$product->id, [
            'artist_id' => $artist->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'currency' => 'usd',
            'is_limited' => true,
            'edition_size' => 10,
            'base_price' => 9.99,
            'is_public' => true,
        ]);

        $response->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_sellable' => 1,
            'sale_status' => 'active',
            'currency' => 'usd',
        ]);
    }
}
