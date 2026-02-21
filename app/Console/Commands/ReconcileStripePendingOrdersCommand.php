<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CommerceStateService;
use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

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

        $stripe = new StripeClient($secret);

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
            try {
                $session = $stripe->checkout->sessions->retrieve($order->stripe_checkout_session_id, []);
            } catch (ApiErrorException $e) {
                continue;
            }

            $status = (string) ($session->status ?? '');
            $paymentStatus = (string) ($session->payment_status ?? '');

            if ($paymentStatus === 'paid') {
                $this->commerceStateService->fulfillPaidOrder($order, [
                    'stripe_payment_intent_id' => (string) ($session->payment_intent ?? $order->stripe_payment_intent_id),
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
}
