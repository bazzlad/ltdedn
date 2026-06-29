<?php

namespace Tests\Feature;

use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OrderShippedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipped_email_uses_support_address_instead_of_replying_to_noreply(): void
    {
        Config::set('mail.support.address', 'support@example.test');

        $order = Order::factory()->create([
            'shipping_carrier' => 'Post Office',
            'shipping_tracking_number' => '123456789',
        ]);

        OrderItem::factory()->for($order)->create([
            'product_name' => 'LTD EDN T SHIRT',
            'sku_code_snapshot' => 'LTD-9-JE-SUIS-EDITION-LIMITE-T-SHIRT',
        ]);

        $mail = new OrderShippedMail($order);
        $html = $mail->render();

        $this->assertStringContainsString('support@example.test', $html);
        $this->assertStringNotContainsString('reply to this email', $html);
        $this->assertSame('support@example.test', $mail->envelope()->replyTo[0]->address);
    }
}
