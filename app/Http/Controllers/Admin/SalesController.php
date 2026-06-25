<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkOrderShippedRequest;
use App\Models\Order;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,paid,exception,cancelled,failed'],
            'source_platform' => ['nullable', 'in:manual,shopify,squarespace,pipe17'],
            'exception' => ['nullable', 'boolean'],
        ]);

        $query = Order::query()->with('connection.artist:id,name')->latest('id');

        if (! empty($filters['q'])) {
            $search = (string) $filters['q'];
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_email', 'like', '%'.$search.'%')
                    ->orWhere('external_order_id', 'like', '%'.$search.'%')
                    ->orWhere('external_order_number', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['source_platform'])) {
            $query->where('source_platform', $filters['source_platform']);
        }

        if ($request->boolean('exception')) {
            $query->whereNotNull('exception_reason');
        }

        $orders = $query->paginate(25)->withQueryString()->through(fn (Order $order) => [
            'id' => $order->id,
            'status' => $order->status?->value,
            'source_platform' => $order->source_platform,
            'external_order_number' => $order->external_order_number,
            'artist_name' => $order->connection?->artist?->name,
            'currency' => $order->currency,
            'total_amount' => (int) $order->total_amount,
            'customer_email' => $order->customer_email,
            'exception_reason' => $order->exception_reason,
            'shipment_pushback_status' => $order->shipment_pushback_status,
            'paid_at' => $order->paid_at ? (string) $order->paid_at : null,
            'shipped_at' => $order->shipped_at ? (string) $order->shipped_at : null,
            'created_at' => (string) $order->created_at,
        ]);

        return Inertia::render('Admin/Sales/Index', [
            'orders' => $orders,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'source_platform' => $filters['source_platform'] ?? '',
                'exception' => $request->boolean('exception'),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'connection.artist:id,name',
            'items.sku:id,sku_code',
            'events' => fn ($query) => $query->latest('id'),
            'events.actor:id,name,email',
        ]);

        return Inertia::render('Admin/Sales/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status?->value,
                'source_platform' => $order->source_platform,
                'external_order_id' => $order->external_order_id,
                'external_order_number' => $order->external_order_number,
                'source_payment_status' => $order->source_payment_status,
                'source_fulfilment_status' => $order->source_fulfilment_status,
                'artist_name' => $order->connection?->artist?->name,
                'currency' => $order->currency,
                'subtotal_amount' => (int) $order->subtotal_amount,
                'shipping_amount' => (int) $order->shipping_amount,
                'tax_amount' => (int) $order->tax_amount,
                'total_amount' => (int) $order->total_amount,
                'shipping_carrier' => $order->shipping_carrier,
                'shipping_tracking_number' => $order->shipping_tracking_number,
                'shipment_pushback_status' => $order->shipment_pushback_status,
                'shipment_pushback_error' => $order->shipment_pushback_error,
                'shipped_at' => $order->shipped_at ? (string) $order->shipped_at : null,
                'shipping_name' => $order->shipping_name,
                'shipping_line1' => $order->shipping_line1,
                'shipping_line2' => $order->shipping_line2,
                'shipping_city' => $order->shipping_city,
                'shipping_state' => $order->shipping_state,
                'shipping_postal_code' => $order->shipping_postal_code,
                'shipping_country' => $order->shipping_country,
                'customer_email' => $order->customer_email,
                'exception_reason' => $order->exception_reason,
                'paid_at' => $order->paid_at ? (string) $order->paid_at : null,
                'created_at' => (string) $order->created_at,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_title_snapshot' => $item->variant_title_snapshot,
                    'sku_code_snapshot' => $item->sku_code_snapshot,
                    'quantity' => (int) $item->quantity,
                    'unit_amount' => (int) $item->unit_amount,
                    'line_total_amount' => (int) $item->line_total_amount,
                    'attributes_snapshot' => $item->attributes_snapshot,
                ])->values(),
                'events' => $order->events->map(fn ($event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'payload' => $event->payload,
                    'actor' => $event->actor ? ['id' => $event->actor->id, 'name' => $event->actor->name] : null,
                    'created_at' => (string) $event->created_at,
                ])->values(),
            ],
        ]);
    }

    public function markShipped(MarkOrderShippedRequest $request, Order $order, OrderFulfillmentService $service): RedirectResponse
    {
        $this->authorize('ship', $order);

        $result = $service->markShipped(
            $order,
            (string) $request->string('carrier'),
            (string) $request->string('tracking'),
            $request->user(),
        );

        if (! $result['ok']) {
            return back()->withErrors(['shipping' => (string) $result['error']]);
        }

        return back()->with('status', 'Order marked as shipped.');
    }
}
