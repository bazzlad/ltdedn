<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').'!')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line('Thank you for creating your account. We\'re excited to have you join our community!')
            ->line('With your account, you can:')
            ->line('• Claim and collect limited edition QR codes')
            ->line('• View your personal collection')
            ->line('• Transfer editions to other users')
            ->action('View Your Dashboard', route('dashboard'))
            ->line('Start building your collection today!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Welcome to '.config('app.name').'! Your account has been created successfully.',
            'action_url' => route('dashboard'),
        ];
    }
}
