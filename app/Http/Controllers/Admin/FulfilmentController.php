<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class FulfilmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Operator workflow: every paid order that hasn't shipped yet, oldest
     * first, with the buyer + address + items inline so a single screen
     * drives the pack-and-ship loop without drilling into Sales.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Order::class);

        $orders = Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereNull('shipped_at')
            ->with(['items:id,order_id,product_name,sku_code_snapshot,quantity,unit_amount,line_total_amount'])
            ->orderBy('paid_at')
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'currency' => $order->currency,
                    'total_amount' => (int) $order->total_amount,
                    'paid_at' => $order->paid_at ? (string) $order->paid_at : null,
                    'customer_email' => $order->customer_email,
                    'shipping_name' => $order->shipping_name,
                    'shipping_phone' => $order->shipping_phone,
                    'shipping_line1' => $order->shipping_line1,
                    'shipping_line2' => $order->shipping_line2,
                    'shipping_city' => $order->shipping_city,
                    'shipping_state' => $order->shipping_state,
                    'shipping_postal_code' => $order->shipping_postal_code,
                    'shipping_country' => $order->shipping_country,
                    'shipping_carrier' => $order->shipping_carrier,
                    'shipping_tracking_number' => $order->shipping_tracking_number,
                    'items' => $order->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'sku_code_snapshot' => $item->sku_code_snapshot,
                        'quantity' => (int) $item->quantity,
                        'line_total_amount' => (int) $item->line_total_amount,
                    ])->values(),
                ];
            })
            ->values();

        return Inertia::render('Admin/Fulfilment/Index', [
            'orders' => $orders,
        ]);
    }
}
