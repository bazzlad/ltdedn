<?php

namespace App\Notifications;

use App\Models\ProductEdition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QRCodeClaimedConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ProductEdition $edition
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
        $artistName = $this->edition->product->artist->name;

        return (new MailMessage)
            ->subject("Welcome! You Now Own Edition #{$editionNumber} of \"{$productName}\"")
            ->greeting("Congratulations, {$notifiable->name}!")
            ->line("You have successfully claimed edition #{$editionNumber} of \"{$productName}\" by {$artistName}.")
            ->line('This limited edition is now yours! You can view it anytime in your dashboard.')
            ->action('View Your Collection', route('dashboard'))
            ->line('Thank you for being part of our community!');
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
            'artist_name' => $this->edition->product->artist->name,
            'message' => "You have successfully claimed edition #{$this->edition->number} of \"{$this->edition->product->name}\"",
        ];
    }
}
