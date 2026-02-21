<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;

class OrderStateService
{
    public function markPaid(Order $order, array $attrs = []): bool
    {
        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        $order->update(array_merge([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ], $attrs));

        return true;
    }

    public function markFailed(Order $order, array $attrs = []): bool
    {
        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        $order->update(array_merge([
            'status' => OrderStatus::Failed,
        ], $attrs));

        return true;
    }
}
