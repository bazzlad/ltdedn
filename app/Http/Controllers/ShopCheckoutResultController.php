<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class ShopCheckoutResultController extends Controller
{
    public function success(Order $order): Response
    {
        return Inertia::render('ShopResult', [
            'status' => 'success',
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
            ],
        ]);
    }

    public function cancel(Order $order): Response
    {
        return Inertia::render('ShopResult', [
            'status' => 'cancel',
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
            ],
        ]);
    }
}
