<?php

namespace App\Notifications;

use App\Models\ProductEditionTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductEditionTransferCancelled extends Notification implements ShouldQueue
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
        $senderName = $this->transfer->sender->name;

        return (new MailMessage)
            ->subject("Transfer Cancelled: Edition #{$editionNumber} of \"{$productName}\"")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$senderName} has cancelled the transfer of edition #{$editionNumber} of \"{$productName}\".")
            ->line('This transfer request is no longer valid.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'message' => "Transfer of Edition #{$this->transfer->productEdition->number} was cancelled by sender",
            'action_url' => route('dashboard'),
        ];
    }
}
