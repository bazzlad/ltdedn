<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArtistMonthlyReportNotification extends Notification implements ShouldQueue
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
            ->subject("Your Monthly Report - {$this->monthName} {$this->year}")
            ->greeting("Hello, {$notifiable->name}!")
            ->line("Here's your monthly performance summary for {$this->monthName} {$this->year}:");

        $message->line("**Editions Claimed:** {$this->reportData['editions_claimed']}")
            ->line("**Total Editions Available:** {$this->reportData['total_editions']}")
            ->line("**Active Products:** {$this->reportData['active_products']}");

        if ($this->reportData['editions_claimed'] > 0) {
            $message->line("**Most Popular Product:** {$this->reportData['most_popular_product']}");
        }

        if ($this->reportData['previous_month_claimed'] > 0) {
            $growth = $this->reportData['editions_claimed'] - $this->reportData['previous_month_claimed'];
            $growthText = $growth > 0 ? "+{$growth}" : $growth;
            $message->line("**Growth vs Last Month:** {$growthText} editions");
        }

        $message->action('View Dashboard', route('admin.dashboard'));

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Monthly report for {$this->monthName} {$this->year}: {$this->reportData['editions_claimed']} editions claimed",
            'month' => $this->monthName,
            'year' => $this->year,
            'report_data' => $this->reportData,
            'action_url' => route('admin.dashboard'),
        ];
    }
}
