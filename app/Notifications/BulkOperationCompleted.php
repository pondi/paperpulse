<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BulkOperationCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $operation;
    protected $count;
    protected $details;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $operation, int $count, array $details = [])
    {
        $this->operation = $operation;
        $this->count = $count;
        $this->details = $details;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        
        if ($notifiable->preference('email_notify_bulk_complete')) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Bulk Operation Completed')
            ->line("Your bulk {$this->operation} operation has been completed.");

        if ($this->operation === 'delete') {
            $message->line("{$this->count} receipts have been deleted.");
        } elseif ($this->operation === 'categorize') {
            $category = $this->details['category'] ?? 'Unknown';
            $message->line("{$this->count} receipts have been categorized as '{$category}'.");
        } elseif ($this->operation === 'export') {
            $format = strtoupper($this->details['format'] ?? 'Unknown');
            $message->line("{$this->count} receipts have been exported to {$format}.");
        }

        return $message
            ->action('View Receipts', route('receipts.index'))
            ->line('Thank you for using PaperPulse!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'bulk_operation_completed',
            'operation' => $this->operation,
            'count' => $this->count,
            'details' => $this->details,
            'created_at' => now()->toISOString(),
        ];
    }
}