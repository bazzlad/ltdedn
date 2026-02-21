<?php

namespace App\Console\Commands;

use App\Models\InventoryReservation;
use App\Models\Order;
use App\Services\CommerceStateService;
use Illuminate\Console\Command;

class ExpireInventoryReservationsCommand extends Command
{
    protected $signature = 'shop:expire-reservations';

    protected $description = 'Expire stale active inventory reservations and release stock';

    public function __construct(private CommerceStateService $commerceStateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expiredIds = InventoryReservation::query()
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->pluck('id');

        foreach ($expiredIds as $reservationId) {
            $reservation = InventoryReservation::find($reservationId);
            if (! $reservation || $reservation->status->value !== 'active') {
                continue;
            }

            $order = Order::find($reservation->order_id);
            if ($order) {
                $this->commerceStateService->failPendingOrder($order, 'reservation_ttl_expired');
            }
        }

        $this->info('Expired reservations processed: '.$expiredIds->count());

        return self::SUCCESS;
    }
}
