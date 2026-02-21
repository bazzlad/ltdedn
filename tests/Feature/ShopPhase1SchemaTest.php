<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\ProductVariantAxis;
use App\Models\ProductVariantValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopPhase1SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase1_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('products', [
            'is_sellable',
            'sale_status',
            'currency',
            'sale_starts_at',
            'sale_ends_at',
        ]));

        $this->assertTrue(Schema::hasTable('product_variant_axes'));
        $this->assertTrue(Schema::hasTable('product_variant_values'));
        $this->assertTrue(Schema::hasTable('product_skus'));

        $this->assertTrue(Schema::hasColumns('order_items', [
            'product_sku_id',
            'sku_code_snapshot',
            'attributes_snapshot',
        ]));

        $this->assertTrue(Schema::hasColumn('orders', 'checkout_expires_at'));
    }

    public function test_product_variant_and_sku_relationships_work(): void
    {
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $axis = ProductVariantAxis::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'sort_order' => 1,
        ]);

        ProductVariantValue::create([
            'product_variant_axis_id' => $axis->id,
            'value' => 'XL',
            'sort_order' => 1,
        ]);

        $sku = ProductSku::create([
            'product_id' => $product->id,
            'sku_code' => 'TEE-BLK-XL',
            'price_amount' => 2999,
            'currency' => 'gbp',
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => [
                'size' => 'XL',
                'color' => 'Black',
            ],
        ]);

        $this->assertSame(1, $product->variantAxes()->count());
        $this->assertSame(1, $axis->values()->count());
        $this->assertSame($product->id, $sku->product->id);
    }

    public function test_sku_stock_helpers_enforce_non_negative_flows(): void
    {
        $sku = ProductSku::factory()->create([
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);

        $this->assertSame(3, $sku->stock_available);
        $this->assertTrue($sku->reserve(1));
        $this->assertSame(2, $sku->fresh()->stock_available);

        $this->assertFalse($sku->fresh()->reserve(3));

        $this->assertTrue($sku->fresh()->release(1));
        $this->assertSame(3, $sku->fresh()->stock_available);

        $freshForConsume = $sku->fresh();
        $freshForConsume->stock_reserved = 1;
        $freshForConsume->save();

        $this->assertTrue($freshForConsume->consume(1));

        $fresh = $freshForConsume->fresh();
        $this->assertSame(2, (int) $fresh->stock_on_hand);
        $this->assertSame(0, (int) $fresh->stock_reserved);
    }
}
