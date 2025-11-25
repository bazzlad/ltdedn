<?php

namespace App\Notifications;

use App\Models\ProductEditionTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductEditionTransferAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProductEditionTransfer $transfer) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $productName = $this->transfer->productEdition->product->name;
        $editionNumber = $this->transfer->productEdition->number;
        $recipientName = $this->transfer->recipient->name;

        return (new MailMessage)
            ->subject("Transfer Accepted: Edition #{$editionNumber} of \"{$productName}\"")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$recipientName} has accepted your transfer of edition #{$editionNumber} of \"{$productName}\".")
            ->line('The transfer is now complete.')
            ->action('View Dashboard', route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'message' => "{$this->transfer->recipient->name} accepted your transfer of Edition #{$this->transfer->productEdition->number}",
            'action_url' => route('dashboard'),
        ];
    }
}
