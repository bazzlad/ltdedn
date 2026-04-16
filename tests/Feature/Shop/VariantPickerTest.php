<?php

namespace Tests\Feature\Shop;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\ProductVariantAxis;
use App\Models\ProductVariantValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VariantPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_product_exposes_variant_axes_and_value_ids_per_sku(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sizeAxis = ProductVariantAxis::create(['product_id' => $product->id, 'name' => 'Size', 'sort_order' => 0]);
        $small = ProductVariantValue::create(['product_variant_axis_id' => $sizeAxis->id, 'value' => 'S', 'sort_order' => 0]);
        $medium = ProductVariantValue::create(['product_variant_axis_id' => $sizeAxis->id, 'value' => 'M', 'sort_order' => 1]);

        $skuS = ProductSku::factory()->create(['product_id' => $product->id, 'sku_code' => 'P1-S', 'is_active' => true]);
        $skuM = ProductSku::factory()->create(['product_id' => $product->id, 'sku_code' => 'P1-M', 'is_active' => true]);
        $skuS->variantValues()->attach($small->id, ['product_variant_axis_id' => $sizeAxis->id]);
        $skuM->variantValues()->attach($medium->id, ['product_variant_axis_id' => $sizeAxis->id]);

        ProductEdition::factory()->create(['product_id' => $product->id, 'product_sku_id' => $skuS->id, 'status' => 'available']);
        ProductEdition::factory()->create(['product_id' => $product->id, 'product_sku_id' => $skuM->id, 'status' => 'available']);

        $response = $this->get(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ShopProduct')
            ->has('product.variant_axes', 1)
            ->where('product.variant_axes.0.name', 'Size')
            ->has('product.variant_axes.0.values', 2)
            ->has('product.skus', 2)
            ->where('product.skus.0.variant_value_ids.0', $small->id)
            ->where('product.skus.1.variant_value_ids.0', $medium->id)
        );
    }

    public function test_legacy_manual_sku_products_still_render_without_variant_axes(): void
    {
        $artist = Artist::factory()->create();
        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);
        $sku = ProductSku::factory()->create(['product_id' => $product->id, 'is_active' => true]);
        ProductEdition::factory()->create(['product_id' => $product->id, 'product_sku_id' => $sku->id, 'status' => 'available']);

        $response = $this->get(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ShopProduct')
            ->has('product.variant_axes', 0)
            ->has('product.skus', 1)
            ->where('product.skus.0.variant_value_ids', [])
        );
    }
}
