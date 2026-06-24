<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExternalOrderExceptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('External order needs review')
            ->line('An external order could not be allocated automatically.')
            ->line('Order #'.$this->order->id.': '.$this->order->exception_reason)
            ->action('Review order', route('admin.sales.show', $this->order));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'source_platform' => $this->order->source_platform,
            'external_order_id' => $this->order->external_order_id,
            'exception_reason' => $this->order->exception_reason,
        ];
    }
}
