<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceivedAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New order #'.$this->order->id.' — '.strtoupper((string) $this->order->currency).' '.number_format(((int) $this->order->total_amount) / 100, 2),
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('items');

        return new Content(
            markdown: 'mail.orders.received_admin',
            with: [
                'order' => $this->order,
                'items' => $this->order->items,
                'currency' => strtoupper((string) $this->order->currency),
            ],
        );
    }
}
