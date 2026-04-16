<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkOrderShippedRequest;
use App\Http\Requests\Admin\RefundOrderRequest;
use App\Models\Order;
use App\Services\OrderFulfillmentService;
use App\Services\OrderRefundService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $validated = $this->validateFilters($request);
        $query = $this->buildFilteredQuery($validated);

        $orders = $query->paginate(25)->withQueryString()->through(function (Order $order) {
            return [
                'id' => $order->id,
                'status' => $order->status ? $order->status->value : null,
                'currency' => $order->currency,
                'total_amount' => (int) $order->total_amount,
                'customer_email' => $order->customer_email,
                'user_name' => $order->user?->name,
                'stripe_checkout_session_id' => $order->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                'paid_at' => $order->paid_at ? (string) $order->paid_at : null,
                'created_at' => (string) $order->created_at,
            ];
        });

        $summaryBase = Order::query();
        if (! empty($validated['from'])) {
            $summaryBase->whereDate('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $summaryBase->whereDate('created_at', '<=', $validated['to']);
        }

        $paidRevenue = (clone $summaryBase)->where('status', 'paid')->sum('total_amount');
        $paidCount = (clone $summaryBase)->where('status', 'paid')->count();
        $pendingCount = (clone $summaryBase)->where('status', 'pending')->count();

        return Inertia::render('Admin/Sales/Index', [
            'orders' => $orders,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'from' => $validated['from'] ?? '',
                'to' => $validated['to'] ?? '',
            ],
            'summary' => [
                'paid_revenue' => (int) $paidRevenue,
                'paid_count' => (int) $paidCount,
                'pending_count' => (int) $pendingCount,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Order::class);

        $validated = $this->validateFilters($request);
        $query = $this->buildFilteredQuery($validated);

        $filename = 'sales-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'status', 'currency', 'total_amount_minor', 'customer_email', 'user_name', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'paid_at', 'created_at']);

            $query->chunk(500, function ($orders) use ($out) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->id,
                        $order->status ? $order->status->value : null,
                        $order->currency,
                        (int) $order->total_amount,
                        $order->customer_email,
                        $order->user?->name,
                        $order->stripe_checkout_session_id,
                        $order->stripe_payment_intent_id,
                        $order->paid_at,
                        $order->created_at,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'user:id,name,email',
            'items.sku:id,sku_code',
            'events' => fn ($q) => $q->latest('id'),
            'events.actor:id,name,email',
        ]);

        return Inertia::render('Admin/Sales/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status ? $order->status->value : null,
                'currency' => $order->currency,
                'subtotal_amount' => (int) $order->subtotal_amount,
                'shipping_amount' => (int) $order->shipping_amount,
                'tax_amount' => (int) $order->tax_amount,
                'total_amount' => (int) $order->total_amount,
                'refunded_amount' => (int) $order->refunded_amount,
                'last_refunded_at' => $order->last_refunded_at ? (string) $order->last_refunded_at : null,
                'shipping_carrier' => $order->shipping_carrier,
                'shipping_tracking_number' => $order->shipping_tracking_number,
                'shipped_at' => $order->shipped_at ? (string) $order->shipped_at : null,
                'shipping_name' => $order->shipping_name,
                'shipping_line1' => $order->shipping_line1,
                'shipping_line2' => $order->shipping_line2,
                'shipping_city' => $order->shipping_city,
                'shipping_postal_code' => $order->shipping_postal_code,
                'shipping_country' => $order->shipping_country,
                'customer_email' => $order->customer_email,
                'user' => $order->user,
                'stripe_checkout_session_id' => $order->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                'paid_at' => $order->paid_at ? (string) $order->paid_at : null,
                'created_at' => (string) $order->created_at,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'product_slug' => $item->product_slug,
                        'sku_code_snapshot' => $item->sku_code_snapshot,
                        'quantity' => (int) $item->quantity,
                        'unit_amount' => (int) $item->unit_amount,
                        'line_total_amount' => (int) $item->line_total_amount,
                        'attributes_snapshot' => $item->attributes_snapshot,
                    ];
                })->values(),
                'events' => $order->events->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'type' => $event->type,
                        'payload' => $event->payload,
                        'actor' => $event->actor ? ['id' => $event->actor->id, 'name' => $event->actor->name] : null,
                        'created_at' => (string) $event->created_at,
                    ];
                })->values(),
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

    public function refund(RefundOrderRequest $request, Order $order, OrderRefundService $service): RedirectResponse
    {
        $this->authorize('refund', $order);

        $result = $service->refund(
            $order,
            $request->amountMinor(),
            (string) $request->string('reason'),
            $request->user(),
        );

        if (! $result['ok']) {
            return back()->withErrors(['refund' => (string) $result['error']]);
        }

        return back()->with('status', 'Refund issued.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,paid,failed,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildFilteredQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Order::query()->with(['user:id,name,email'])->latest('id');

        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('customer_email', 'like', '%'.$q.'%')
                    ->orWhere('stripe_checkout_session_id', 'like', '%'.$q.'%')
                    ->orWhere('stripe_payment_intent_id', 'like', '%'.$q.'%')
                    ->orWhereHas('user', function ($userQ) use ($q) {
                        $userQ->where('name', 'like', '%'.$q.'%')
                            ->orWhere('email', 'like', '%'.$q.'%');
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query;
    }
}
