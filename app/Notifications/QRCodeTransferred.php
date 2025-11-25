<?php

namespace App\Notifications;

use App\Models\ProductEdition;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QRCodeTransferred extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ProductEdition $edition,
        public User $sender,
        public string $token
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $productName = $this->edition->product->name;
        $editionNumber = $this->edition->number;
        $senderName = $this->sender->name;

        return (new MailMessage)
            ->subject("Transfer Request: Edition #{$editionNumber} of \"{$productName}\"")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$senderName} wants to transfer edition #{$editionNumber} of \"{$productName}\" to you.")
            ->line('You have 48 hours to accept this transfer.')
            ->action('Review Transfer', route('transfers.accept', $this->token))
            ->line('If you do not accept within 48 hours, the transfer will be automatically cancelled.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'edition_id' => $this->edition->id,
            'product_id' => $this->edition->product_id,
            'product_name' => $this->edition->product->name,
            'edition_number' => $this->edition->number,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'token' => $this->token,
            'type' => 'transfer_request',
            'message' => "{$this->sender->name} sent you a transfer request for Edition #{$this->edition->number} of \"{$this->edition->product->name}\"",
            'action_url' => route('transfers.accept', $this->token),
        ];
    }
}
