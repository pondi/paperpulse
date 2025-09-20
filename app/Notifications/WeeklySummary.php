<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklySummary extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $summaryData;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $summaryData)
    {
        $this->summaryData = $summaryData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = [];

        // Add in-app notification if user wants it
        if ($notifiable->preference('notify_weekly_summary_ready', true)) {
            $channels[] = 'database';
        }

        // Add email if user wants it
        if ($notifiable->preference('email_notify_weekly_summary', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $data = $this->summaryData;
        $weekStart = $data['week_start']->format('M j');
        $weekEnd = $data['week_end']->format('M j, Y');

        $message = (new MailMessage)
            ->subject("Your Weekly Receipt Summary ({$weekStart} - {$weekEnd})")
            ->line("Here's your weekly receipt summary for {$weekStart} - {$weekEnd}:")
            ->line("📝 **{$data['total_receipts']}** receipts processed")
            ->line("💰 **{$this->formatCurrency($data['total_amount'], $data['currency'])}** total spending");

        if ($data['total_receipts'] > 0) {
            $message->line("📊 **{$this->formatCurrency($data['average_amount'], $data['currency'])}** average per receipt");

            // Add top categories
            if ($data['categories']->count() > 0) {
                $topCategories = $data['categories']->sortDesc()->take(3);
                $message->line("\n**Top Categories:**");
                foreach ($topCategories as $category => $count) {
                    $message->line("• {$category}: {$count} receipts");
                }
            }

            // Add top merchants
            if ($data['merchants']->count() > 0) {
                $topMerchants = $data['merchants']->sortDesc()->take(3);
                $message->line("\n**Top Merchants:**");
                foreach ($topMerchants as $merchant => $count) {
                    $message->line("• {$merchant}: {$count} receipts");
                }
            }
        }

        $message->action('View All Receipts', route('receipts.index'))
            ->line('Thank you for using PaperPulse!');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $data = $this->summaryData;

        return [
            'type' => 'weekly_summary',
            'week_start' => $data['week_start']->toDateString(),
            'week_end' => $data['week_end']->toDateString(),
            'total_receipts' => $data['total_receipts'],
            'total_amount' => $data['total_amount'],
            'currency' => $data['currency'],
            'average_amount' => $data['average_amount'],
            'categories_count' => $data['categories']->count(),
            'merchants_count' => $data['merchants']->count(),
        ];
    }

    /**
     * Format currency according to user preference
     */
    private function formatCurrency($amount, $currency = 'NOK'): string
    {
        return number_format($amount, 2).' '.$currency;
    }
}
