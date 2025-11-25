<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminMonthlyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $reportData,
        public string $monthName,
        public int $year
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
            ->subject("Platform Monthly Report - {$this->monthName} {$this->year}")
            ->greeting("Hello, {$notifiable->name}!")
            ->line("Here's the platform-wide performance summary for {$this->monthName} {$this->year}:");

        $message->line("**Total Editions Claimed:** {$this->reportData['total_editions_claimed']}")
            ->line("**Total Active Products:** {$this->reportData['total_active_products']}")
            ->line("**Active Artists:** {$this->reportData['active_artists']}")
            ->line("**New Users Registered:** {$this->reportData['new_users']}")
            ->line("**Total Platform Users:** {$this->reportData['total_users']}");

        if (! empty($this->reportData['top_artist'])) {
            $message->line("**Top Performing Artist:** {$this->reportData['top_artist']} ({$this->reportData['top_artist_claims']} claims)");
        }

        if ($this->reportData['previous_month_claimed'] > 0) {
            $growth = $this->reportData['total_editions_claimed'] - $this->reportData['previous_month_claimed'];
            $growthText = $growth > 0 ? "+{$growth}" : $growth;
            $message->line("**Growth vs Last Month:** {$growthText} editions");
        }

        $message->action('View Admin Dashboard', route('admin.dashboard'));

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Platform monthly report for {$this->monthName} {$this->year}: {$this->reportData['total_editions_claimed']} editions claimed",
            'month' => $this->monthName,
            'year' => $this->year,
            'report_data' => $this->reportData,
            'action_url' => route('admin.dashboard'),
        ];
    }
}
