<?php

namespace App\Notifications;

use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceiptSharedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Receipt $receipt;
    protected User $sharedBy;
    protected FileShare $share;

    /**
     * Create a new notification instance.
     */
    public function __construct(Receipt $receipt, User $sharedBy, FileShare $share)
    {
        $this->receipt = $receipt;
        $this->sharedBy = $sharedBy;
        $this->share = $share;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $receiptTitle = $this->receipt->merchant?->name ?? 'Receipt #' . $this->receipt->id;
        
        return (new MailMessage)
            ->subject('Receipt Shared: ' . $receiptTitle)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($this->sharedBy->name . ' has shared a receipt with you.')
            ->line('Receipt: ' . $receiptTitle)
            ->when($this->receipt->total_amount, function ($message) {
                return $message->line('Amount: ' . number_format($this->receipt->total_amount, 2) . ' ' . $this->receipt->currency);
            })
            ->when($this->receipt->receipt_date, function ($message) {
                return $message->line('Date: ' . $this->receipt->receipt_date->format('Y-m-d'));
            })
            ->line('Permission: ' . ucfirst($this->share->permission))
            ->when($this->share->expires_at, function ($message) {
                return $message->line('Expires: ' . $this->share->expires_at->format('Y-m-d H:i'));
            })
            ->action('View Receipt', route('receipts.show', $this->receipt))
            ->line('Thank you for using PaperPulse!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'receipt_shared',
            'receipt_id' => $this->receipt->id,
            'receipt_title' => $this->receipt->merchant?->name ?? 'Receipt #' . $this->receipt->id,
            'total_amount' => $this->receipt->total_amount,
            'currency' => $this->receipt->currency,
            'receipt_date' => $this->receipt->receipt_date?->toISOString(),
            'shared_by_id' => $this->sharedBy->id,
            'shared_by_name' => $this->sharedBy->name,
            'permission' => $this->share->permission,
            'expires_at' => $this->share->expires_at?->toISOString(),
        ];
    }
}