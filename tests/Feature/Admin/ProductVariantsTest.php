<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\ProductVariantValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function adminAndProduct(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->create(['artist_id' => $artist->id]);

        return [$admin, $product];
    }

    public function test_admin_can_create_axes_and_values(): void
    {
        [$admin, $product] = $this->adminAndProduct();

        $this->actingAs($admin)->post(
            route('admin.products.variants.axes', $product),
            [
                'axes' => [
                    ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M'], ['value' => 'L']]],
                    ['name' => 'Colour', 'values' => [['value' => 'Red'], ['value' => 'Blue']]],
                ],
            ],
        )->assertRedirect();

        $this->assertSame(2, $product->variantAxes()->count());
        $this->assertSame(
            ['Size', 'Colour'],
            $product->variantAxes()->get()->pluck('name')->toArray(),
        );
        $this->assertSame(3, $product->variantAxes()->where('name', 'Size')->first()->values()->count());
    }

    public function test_regenerate_creates_cartesian_skus_idempotently(): void
    {
        [$admin, $product] = $this->adminAndProduct();

        $this->actingAs($admin)->post(route('admin.products.variants.axes', $product), [
            'axes' => [
                ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M']]],
                ['name' => 'Colour', 'values' => [['value' => 'Red'], ['value' => 'Blue']]],
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.products.variants.regenerate', $product), [
            'default_price_amount' => 4500,
            'currency' => 'gbp',
            'default_stock_on_hand' => 10,
        ]);

        $this->assertSame(4, $product->skus()->count());

        // Re-run; no new SKUs.
        $this->actingAs($admin)->post(route('admin.products.variants.regenerate', $product), [
            'default_price_amount' => 4500,
        ]);
        $this->assertSame(4, $product->skus()->count());
    }

    public function test_regenerate_deactivates_skus_with_orders_when_no_longer_reachable(): void
    {
        [$admin, $product] = $this->adminAndProduct();

        $this->actingAs($admin)->post(route('admin.products.variants.axes', $product), [
            'axes' => [
                ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M']]],
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.products.variants.regenerate', $product), [
            'default_price_amount' => 4500,
        ]);

        $this->assertSame(2, $product->skus()->count());

        $sizeAxis = $product->variantAxes()->first();
        $mValue = $sizeAxis->values()->where('value', 'M')->first();
        $mSku = ProductSku::query()
            ->where('product_id', $product->id)
            ->whereHas('variantValues', fn ($q) => $q->where('product_variant_values.id', $mValue->id))
            ->first();

        OrderItem::create([
            'order_id' => \App\Models\Order::create([
                'status' => 'paid',
                'currency' => 'gbp',
                'subtotal_amount' => 4500,
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 4500,
                'paid_at' => now(),
            ])->id,
            'product_id' => $product->id,
            'product_sku_id' => $mSku->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'sku_code_snapshot' => $mSku->sku_code,
            'quantity' => 1,
            'unit_amount' => 4500,
            'line_total_amount' => 4500,
        ]);

        // Now remove the M value by sending only S back.
        $axisId = $sizeAxis->id;
        $sValue = $sizeAxis->values()->where('value', 'S')->first();

        $this->actingAs($admin)->post(route('admin.products.variants.axes', $product), [
            'axes' => [[
                'id' => $axisId,
                'name' => 'Size',
                'values' => [['id' => $sValue->id, 'value' => 'S']],
            ]],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.products.variants.regenerate', $product), [
            'default_price_amount' => 4500,
        ])->assertRedirect();

        // M SKU must still exist (it has an order), but be inactive.
        $this->assertDatabaseHas('product_skus', ['id' => $mSku->id, 'is_active' => false]);
    }

    public function test_non_admin_cannot_sync_variants(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $artist = Artist::factory()->create();
        $product = Product::factory()->create(['artist_id' => $artist->id]);

        $this->actingAs($user)->post(route('admin.products.variants.axes', $product), [
            'axes' => [],
        ])->assertForbidden();
    }

    public function test_resolve_sku_by_axis_values_via_service(): void
    {
        [$admin, $product] = $this->adminAndProduct();

        $this->actingAs($admin)->post(route('admin.products.variants.axes', $product), [
            'axes' => [
                ['name' => 'Size', 'values' => [['value' => 'S'], ['value' => 'M']]],
            ],
        ]);
        $this->actingAs($admin)->post(route('admin.products.variants.regenerate', $product), [
            'default_price_amount' => 4500,
        ]);

        $mValue = ProductVariantValue::query()->where('value', 'M')->firstOrFail();

        /** @var \App\Services\VariantService $service */
        $service = app(\App\Services\VariantService::class);
        $sku = $service->resolveSkuByAxisValues($product, [$mValue->id]);

        $this->assertNotNull($sku);
        $this->assertTrue($sku->variantValues->contains('id', $mValue->id));
    }
}
