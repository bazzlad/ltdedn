<?php

namespace App\Notifications;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreatedByAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $plainPassword,
        public UserRole $role
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
        $message = (new MailMessage)
            ->subject('Your Account Has Been Created')
            ->greeting("Hello, {$notifiable->name}!")
            ->line('An account has been created for you by an administrator.')
            ->line("**Email:** {$notifiable->email}")
            ->line("**Temporary Password:** {$this->plainPassword}")
            ->line("**Role:** {$this->role->label()}");

        if ($this->role === UserRole::Admin) {
            $message->line('As an administrator, you have full access to manage users, artists, products, and editions.')
                ->action('Access Admin Dashboard', route('admin.dashboard'));
        } elseif ($this->role === UserRole::Artist) {
            $message->line('As an artist, you can create and manage products and editions.')
                ->action('Access Admin Dashboard', route('admin.dashboard'));
        } else {
            $message->line('You can now claim QR codes and build your collection.')
                ->action('View Your Dashboard', route('dashboard'));
        }

        $message->line('**Important:** Please change your password after logging in for the first time.');

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Your account has been created by an administrator. Please check your email for login credentials.',
            'role' => $this->role->value,
            'action_url' => $this->role === UserRole::User ? route('dashboard') : route('admin.dashboard'),
        ];
    }
}
