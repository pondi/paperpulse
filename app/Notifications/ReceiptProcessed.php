<?php

namespace App\Notifications;

use App\Models\Receipt;
use Illuminate\Notifications\Messages\MailMessage;

class ReceiptProcessed extends TemplatedNotification
{
    protected $receiptData;

    protected $success;

    protected $errorMessage;

    /**
     * Create a new notification instance.
     */
    public function __construct(Receipt $receipt, bool $success = true, ?string $errorMessage = null)
    {
        // Store only the data we need, not the full model
        $this->receiptData = [
            'id' => $receipt->id,
            'merchant_name' => $receipt->merchant?->name ?? 'Unknown',
            'total_amount' => $receipt->total_amount ?? 0,
            'currency' => $receipt->currency ?? 'NOK',
        ];
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
     * Get the email template key for this notification
     */
    protected function getEmailTemplateKey(): string
    {
        return $this->success ? 'receipt_processed_success' : 'receipt_processed_failed';
    }

    /**
     * Get the variables to pass to the email template
     */
    protected function getEmailVariables($notifiable): array
    {
        $variables = [
            'merchant_name' => $this->receiptData['merchant_name'],
            'amount' => number_format($this->receiptData['total_amount'], 2),
            'currency' => $this->receiptData['currency'],
            'receipt_url' => route('receipts.show', $this->receiptData['id']),
        ];

        if (! $this->success) {
            $variables['error_message'] = $this->errorMessage ?? 'Unknown error';
        }

        return $variables;
    }

    /**
     * Get fallback mail message if template is not found
     */
    protected function getFallbackMail($notifiable): MailMessage
    {
        if ($this->success) {
            return (new MailMessage)
                ->subject('Receipt Processed Successfully')
                ->line('Your receipt has been processed successfully.')
                ->line('Merchant: '.$this->receiptData['merchant_name'])
                ->line('Amount: '.number_format($this->receiptData['total_amount'], 2).' '.$this->receiptData['currency'])
                ->action('View Receipt', route('receipts.show', $this->receiptData['id']))
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
            'receipt_id' => $this->receiptData['id'],
            'merchant_name' => $this->receiptData['merchant_name'],
            'amount' => $this->receiptData['total_amount'],
            'currency' => $this->receiptData['currency'],
            'success' => $this->success,
            'error_message' => $this->errorMessage,
            'created_at' => now()->toISOString(),
        ];
    }
}
