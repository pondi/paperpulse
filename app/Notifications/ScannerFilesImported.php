<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScannerFilesImported extends Notification implements ShouldQueue
{
    use Queueable;

    protected $fileCount;
    protected $processedCount;
    protected $failedCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $fileCount, int $processedCount = 0, int $failedCount = 0)
    {
        $this->fileCount = $fileCount;
        $this->processedCount = $processedCount;
        $this->failedCount = $failedCount;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        
        if ($notifiable->preference('email_notify_scanner_import')) {
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
            ->subject('Scanner Files Imported')
            ->line("{$this->fileCount} files have been imported from your scanner.");

        if ($this->processedCount > 0) {
            $message->line("{$this->processedCount} files were successfully processed.");
        }

        if ($this->failedCount > 0) {
            $message->line("{$this->failedCount} files failed to process.");
        }

        return $message
            ->action('View Scanner Imports', route('pulsedav.index'))
            ->line('Thank you for using PaperPulse!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'scanner_files_imported',
            'file_count' => $this->fileCount,
            'processed_count' => $this->processedCount,
            'failed_count' => $this->failedCount,
            'created_at' => now()->toISOString(),
        ];
    }
}