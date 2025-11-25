<?php

namespace App\Notifications;

use App\Models\ProductEdition;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QRCodeClaimed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ProductEdition $edition,
        public User $claimer
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
        $claimerName = $this->claimer->name;
        $claimerEmail = $this->claimer->email;

        return (new MailMessage)
            ->subject("Edition #{$editionNumber} of \"{$productName}\" Has Been Claimed!")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Great news! Edition #{$editionNumber} of your product \"{$productName}\" has been claimed.")
            ->line("**Claimed by:** {$claimerName} ({$claimerEmail})")
            ->action('View Product', route('admin.products.show', $this->edition->product_id))
            ->line('This edition is now owned by the claimer and they can view it in their dashboard.');
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
            'claimer_id' => $this->claimer->id,
            'claimer_name' => $this->claimer->name,
            'claimer_email' => $this->claimer->email,
            'message' => "Edition #{$this->edition->number} of \"{$this->edition->product->name}\" has been claimed by {$this->claimer->name}",
        ];
    }
}
