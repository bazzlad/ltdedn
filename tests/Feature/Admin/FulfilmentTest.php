<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfilment_index_lists_paid_unshipped_orders_oldest_first(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create();
        $sku = ProductSku::factory()->create(['product_id' => $product->id]);

        $newer = $this->makePaidOrder($product, $sku, [
            'paid_at' => now()->subHour(),
            'shipping_name' => 'Newer Buyer',
        ]);
        $older = $this->makePaidOrder($product, $sku, [
            'paid_at' => now()->subDay(),
            'shipping_name' => 'Older Buyer',
        ]);
        $alreadyShipped = $this->makePaidOrder($product, $sku, [
            'paid_at' => now()->subDays(2),
            'shipped_at' => now()->subDay(),
            'shipping_name' => 'Already Done',
        ]);
        $pending = Order::create([
            'status' => 'pending',
            'currency' => 'gbp',
            'subtotal_amount' => 1000,
            'shipping_amount' => 0,
            'total_amount' => 1000,
            'customer_email' => 'pending@example.com',
        ]);

        $response = $this->actingAs($admin)->get('/admin/fulfilment');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Fulfilment/Index')
            ->has('orders', 2)
            ->where('orders.0.id', $older->id)
            ->where('orders.1.id', $newer->id)
            ->where('orders.0.shipping_name', 'Older Buyer')
        );

        // Sanity: shipped + pending excluded
        $this->assertNotContains($alreadyShipped->id, collect($response->viewData('page')['props']['orders'])->pluck('id')->all());
        $this->assertNotContains($pending->id, collect($response->viewData('page')['props']['orders'])->pluck('id')->all());
    }

    public function test_non_admin_cannot_access_fulfilment(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);

        $this->actingAs($artist)->get('/admin/fulfilment')->assertStatus(403);
    }

    public function test_inertia_shares_fulfilment_queue_count_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create();
        $sku = ProductSku::factory()->create(['product_id' => $product->id]);
        $this->makePaidOrder($product, $sku, ['paid_at' => now()->subHour()]);
        $this->makePaidOrder($product, $sku, ['paid_at' => now()->subMinutes(30)]);
        $this->makePaidOrder($product, $sku, ['shipped_at' => now()]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertInertia(fn (Assert $page) => $page->where('fulfilmentQueueCount', 2));
    }

    private function makePaidOrder(Product $product, ProductSku $sku, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'status' => 'paid',
            'currency' => 'gbp',
            'subtotal_amount' => 1999,
            'shipping_amount' => 0,
            'total_amount' => 1999,
            'customer_email' => 'buyer@example.com',
            'paid_at' => now(),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'sku_code_snapshot' => $sku->sku_code,
            'quantity' => 1,
            'unit_amount' => 1999,
            'line_total_amount' => 1999,
        ]);

        return $order;
    }
}
