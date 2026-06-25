<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\Pipe17\Pipe17Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;

class PushPipe17Fulfilment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $orderId) {}

    public function handle(Pipe17Client $client): void
    {
        $order = Order::query()->with(['connection', 'items'])->find($this->orderId);

        if (! $order || ! $order->connection) {
            return;
        }

        try {
            $client->createFulfillment($order);
        } catch (RequestException $exception) {
            $this->recordFailure($order, 'Pipe17 pushback failed: '.$exception->getMessage());

            if ($exception->response->serverError()) {
                throw $exception;
            }

            return;
        } catch (\Throwable $throwable) {
            $this->recordFailure($order, 'Pipe17 pushback failed: '.$throwable->getMessage());

            throw $throwable;
        }

        $this->recordSuccess($order);
    }

    private function recordSuccess(Order $order): void
    {
        $order->update([
            'shipment_pushback_status' => 'succeeded',
            'shipment_pushback_error' => null,
            'last_pushback_attempted_at' => now(),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'type' => 'shipment_pushback_success',
            'payload' => ['platform' => 'pipe17'],
        ]);
    }

    private function recordFailure(Order $order, string $error): void
    {
        $order->update([
            'shipment_pushback_status' => 'failed',
            'shipment_pushback_error' => $error,
            'last_pushback_attempted_at' => now(),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'type' => 'shipment_pushback_failure',
            'payload' => ['platform' => 'pipe17', 'error' => $error],
        ]);
    }
}
