<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your LTD/EDN order #'.$this->order->id.' has shipped',
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('items');

        return new Content(
            markdown: 'mail.orders.shipped',
            with: [
                'order' => $this->order,
                'items' => $this->order->items,
                'currency' => strtoupper((string) $this->order->currency),
            ],
        );
    }
}
