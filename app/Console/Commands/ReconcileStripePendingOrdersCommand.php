<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CommerceStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ReconcileStripePendingOrdersCommand extends Command
{
    protected $signature = 'shop:reconcile-pending-orders {--limit=100}';

    protected $description = 'Reconcile stale pending orders against Stripe checkout sessions';

    public function __construct(private CommerceStateService $commerceStateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            $this->error('Missing STRIPE_SECRET');

            return self::FAILURE;
        }

        $orders = Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereNotNull('stripe_checkout_session_id')
            ->whereNotNull('checkout_expires_at')
            ->where('checkout_expires_at', '<', now()->subMinutes(2))
            ->limit((int) $this->option('limit'))
            ->get();

        $paid = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $request = Http::withToken($secret);
            $apiVersion = (string) config('services.stripe.api_version', '');
            if ($apiVersion !== '') {
                $request = $request->withHeaders(['Stripe-Version' => $apiVersion]);
            }

            $response = $request->get('https://api.stripe.com/v1/checkout/sessions/'.$order->stripe_checkout_session_id);
            if (! $response->successful()) {
                continue;
            }

            $session = (array) $response->json();
            $status = (string) ($session['status'] ?? '');
            $paymentStatus = (string) ($session['payment_status'] ?? '');

            if ($paymentStatus === 'paid') {
                // Copy customer_email, shipping address, tax/total from the
                // session onto the order before marking it paid — webhook
                // path does this via extractSessionFieldsForOrder; we
                // replicate here so reconciled orders aren't missing
                // customer details when the webhook never arrived.
                $this->enrichOrderFromSession($order, $session);

                $this->commerceStateService->fulfillPaidOrder($order, [
                    'stripe_payment_intent_id' => (string) ($session['payment_intent'] ?? $order->stripe_payment_intent_id),
                ]);
                $paid++;

                continue;
            }

            if ($status === 'expired' || ($status === 'complete' && $paymentStatus !== 'paid')) {
                $this->commerceStateService->failPendingOrder($order, 'reconciliation_'.$status);
                $failed++;
            }
        }

        $this->info('Reconciled pending orders. paid='.$paid.' failed='.$failed.' scanned='.$orders->count());

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function enrichOrderFromSession(Order $order, array $session): void
    {
        $updates = [];

        if (! $order->customer_email) {
            $email = (string) data_get($session, 'customer_details.email', '');
            if ($email !== '') {
                $updates['customer_email'] = $email;
            }
        }

        foreach (['subtotal_amount' => 'amount_subtotal', 'total_amount' => 'amount_total'] as $col => $path) {
            $val = data_get($session, $path);
            if ($val !== null) {
                $updates[$col] = (int) $val;
            }
        }

        $tax = data_get($session, 'total_details.amount_tax');
        if ($tax !== null) {
            $updates['tax_amount'] = (int) $tax;
        }

        $shippingTotal = data_get($session, 'shipping_cost.amount_total');
        if ($shippingTotal !== null) {
            $updates['shipping_amount'] = (int) $shippingTotal;
        }

        $shippingRateId = (string) data_get($session, 'shipping_cost.shipping_rate', '');
        if ($shippingRateId !== '') {
            $updates['shipping_rate_id'] = $shippingRateId;
        }

        $addressMap = [
            'shipping_name' => 'shipping_details.name',
            'shipping_line1' => 'shipping_details.address.line1',
            'shipping_line2' => 'shipping_details.address.line2',
            'shipping_city' => 'shipping_details.address.city',
            'shipping_state' => 'shipping_details.address.state',
            'shipping_postal_code' => 'shipping_details.address.postal_code',
            'shipping_country' => 'shipping_details.address.country',
        ];

        foreach ($addressMap as $col => $path) {
            $val = data_get($session, $path);
            if ($val !== null && $val !== '') {
                $updates[$col] = (string) $val;
            }
        }

        $phone = (string) data_get($session, 'customer_details.phone', '');
        if ($phone !== '' && ! $order->shipping_phone) {
            $updates['shipping_phone'] = $phone;
        }

        if ($updates !== []) {
            $order->update($updates);
            $order->refresh();
        }
    }
}
