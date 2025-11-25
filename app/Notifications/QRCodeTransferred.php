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
        public User $sender
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
            ->subject("Edition #{$editionNumber} of \"{$productName}\" Has Been Transferred to You!")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Great news! {$senderName} has transferred edition #{$editionNumber} of \"{$productName}\" to you.")
            ->line('This edition is now in your collection.')
            ->action('View Your Collection', route('dashboard'))
            ->line('Enjoy your new edition!');
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
            'message' => "Edition #{$this->edition->number} of \"{$this->edition->product->name}\" has been transferred to you by {$this->sender->name}",
        ];
    }
}
