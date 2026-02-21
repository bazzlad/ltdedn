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

class SalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_sales_index_and_detail(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create();
        $sku = ProductSku::factory()->create(['product_id' => $product->id]);

        $order = Order::create([
            'status' => 'paid',
            'currency' => 'gbp',
            'subtotal_amount' => 1999,
            'shipping_amount' => 0,
            'total_amount' => 1999,
            'customer_email' => 'buyer@example.com',
        ]);

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

        $index = $this->actingAs($admin)->get('/admin/sales');
        $index->assertStatus(200);
        $index->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sales/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
            ->where('summary.paid_count', 1)
        );

        $show = $this->actingAs($admin)->get('/admin/sales/'.$order->id);
        $show->assertStatus(200);
        $show->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sales/Show')
            ->where('order.id', $order->id)
            ->has('order.items', 1)
        );
    }

    public function test_non_admin_cannot_access_sales_pages(): void
    {
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $order = Order::create([
            'status' => 'pending',
            'currency' => 'gbp',
            'subtotal_amount' => 1000,
            'shipping_amount' => 0,
            'total_amount' => 1000,
            'customer_email' => 'buyer@example.com',
        ]);

        $this->actingAs($artist)->get('/admin/sales')->assertStatus(403);
        $this->actingAs($artist)->get('/admin/sales/'.$order->id)->assertStatus(403);
    }
}
