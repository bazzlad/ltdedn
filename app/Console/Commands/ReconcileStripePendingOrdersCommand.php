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
                $this->commerceStateService->fulfillFromSession($order, $session);
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
