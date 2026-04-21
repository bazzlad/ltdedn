<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderFulfillmentService
{
    /**
     * Record a shipment on a paid order. Updates carrier/tracking fields,
     * stamps shipped_at, and writes an OrderEvent. Idempotent-ish: calling
     * again will update the tracking fields and log a new event.
     *
     * On the first ship (when shipped_at is being set), queues an
     * OrderShippedMail to the buyer. Re-saving tracking on an already-shipped
     * order does not re-send.
     *
     * @return array{ok: bool, error?: string}
     */
    public function markShipped(Order $order, string $carrier, string $tracking, User $actor): array
    {
        if ($order->status !== OrderStatus::Paid) {
            return ['ok' => false, 'error' => 'Order must be paid before it can be shipped.'];
        }

        if ((int) $order->refunded_amount >= (int) $order->total_amount && (int) $order->total_amount > 0) {
            return ['ok' => false, 'error' => 'Order has been fully refunded and cannot be shipped.'];
        }

        DB::transaction(function () use ($order, $carrier, $tracking, $actor) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked) {
                return;
            }

            $isFirstShip = $locked->shipped_at === null;
            $previousTracking = (string) $locked->shipping_tracking_number;

            $locked->update([
                'shipping_carrier' => $carrier,
                'shipping_tracking_number' => $tracking,
                'shipped_at' => $locked->shipped_at ?: now(),
            ]);

            OrderEvent::create([
                'order_id' => $locked->id,
                'user_id' => $actor->id,
                'type' => $previousTracking === '' ? 'shipped' : 'shipping_updated',
                'payload' => [
                    'carrier' => $carrier,
                    'tracking' => $tracking,
                    'previous_tracking' => $previousTracking ?: null,
                ],
            ]);

            if ($isFirstShip) {
                DB::afterCommit(fn () => $this->dispatchShippedNotification($locked->fresh()));
            }
        });

        return ['ok' => true];
    }

    /**
     * Queue the shipped-confirmation email to the buyer exactly once.
     * Stamped on order meta so a manual re-trigger or duplicate save can't
     * spam them.
     */
    private function dispatchShippedNotification(?Order $order): void
    {
        if (! $order || ! $order->customer_email) {
            return;
        }

        $meta = is_array($order->meta) ? $order->meta : [];
        if (! empty($meta['shipped_mailed_at'])) {
            return;
        }

        Mail::to((string) $order->customer_email)->queue(new OrderShippedMail($order));

        $meta['shipped_mailed_at'] = now()->toIso8601String();
        $order->update(['meta' => $meta]);
    }
}
