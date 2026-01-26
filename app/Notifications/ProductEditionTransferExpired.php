<?php

namespace App\Notifications;

use App\Models\ProductEditionTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductEditionTransferExpired extends Notification implements ShouldQueue
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

        $message = $notifiable->id === $this->transfer->sender_id
            ? "The transfer of edition #{$editionNumber} of \"{$productName}\" has expired and been returned to your collection."
            : "The transfer request for edition #{$editionNumber} of \"{$productName}\" has expired.";

        return (new MailMessage)
            ->subject("Transfer Expired: Edition #{$editionNumber} of \"{$productName}\"")
            ->greeting("Hello {$notifiable->name}!")
            ->line($message)
            ->action('View Dashboard', route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'message' => "Transfer of Edition #{$this->transfer->productEdition->number} has expired",
            'action_url' => route('dashboard'),
        ];
    }
}
