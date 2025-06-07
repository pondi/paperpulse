<?php

namespace App\Notifications;

use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceiptProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    protected $receipt;

    protected $success;

    protected $errorMessage;

    /**
     * Create a new notification instance.
     */
    public function __construct(Receipt $receipt, bool $success = true, ?string $errorMessage = null)
    {
        $this->receipt = $receipt;
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($this->success && $notifiable->preference('email_notify_processing_complete')) {
            $channels[] = 'mail';
        } elseif (! $this->success && $notifiable->preference('email_notify_processing_failed')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        if ($this->success) {
            return (new MailMessage)
                ->subject('Receipt Processed Successfully')
                ->line('Your receipt has been processed successfully.')
                ->line('Merchant: '.($this->receipt->merchant?->name ?? 'Unknown'))
                ->line('Amount: '.number_format($this->receipt->total_amount, 2).' '.$this->receipt->currency)
                ->action('View Receipt', route('receipts.show', $this->receipt->id))
                ->line('Thank you for using PaperPulse!');
        } else {
            return (new MailMessage)
                ->subject('Receipt Processing Failed')
                ->error()
                ->line('We encountered an error while processing your receipt.')
                ->line('Error: '.($this->errorMessage ?? 'Unknown error'))
                ->line('Please try uploading the receipt again or contact support if the issue persists.')
                ->action('Upload New Receipt', route('documents.upload'));
        }
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => $this->success ? 'receipt_processed' : 'receipt_failed',
            'receipt_id' => $this->receipt->id,
            'merchant_name' => $this->receipt->merchant?->name ?? 'Unknown',
            'amount' => $this->receipt->total_amount,
            'currency' => $this->receipt->currency,
            'success' => $this->success,
            'error_message' => $this->errorMessage,
            'created_at' => now()->toISOString(),
        ];
    }
}
