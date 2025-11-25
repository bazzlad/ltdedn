<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EditionsSoldOutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Product $product
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
        $editionCount = $this->product->edition_size;

        return (new MailMessage)
            ->subject("🎉 Sold Out! All Editions of '{$this->product->name}' Are Claimed!")
            ->greeting("Congratulations, {$notifiable->name}!")
            ->line("All **{$editionCount}** editions of your product **{$this->product->name}** have been claimed!")
            ->line('This is a huge milestone. Your work has been fully collected by the community.')
            ->action('View Product Details', route('admin.products.show', $this->product->id))
            ->line('Thank you for being part of our platform!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "All {$this->product->edition_size} editions of '{$this->product->name}' have been claimed!",
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'edition_count' => $this->product->edition_size,
            'action_url' => route('admin.products.show', $this->product->id),
        ];
    }
}
