<?php

namespace App\Notifications;

use App\Models\Warranty;
use Illuminate\Notifications\Messages\MailMessage;

class WarrantyEndingNotification extends TemplatedNotification
{
    protected array $warrantyData;

    protected int $daysRemaining;

    public function __construct(Warranty $warranty, int $daysRemaining)
    {
        // Link directly to the warranty show page
        $fileUrl = route('warranties.show', $warranty->id);

        $this->warrantyData = [
            'id' => $warranty->id,
            'file_id' => $warranty->file_id,
            'product_name' => $warranty->product_name ?? 'Unknown',
            'manufacturer' => $warranty->manufacturer ?? 'Unknown',
            'warranty_end_date' => $warranty->warranty_end_date?->toDateString(),
            'file_url' => $fileUrl,
        ];
        $this->daysRemaining = $daysRemaining;
    }

    public function via($notifiable): array
    {
        $channels = [];

        if ($notifiable->preference('notify_warranty_expiring', true)) {
            $channels[] = 'database';
        }

        if ($notifiable->preference('email_notify_warranty_expiring', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    protected function getEmailTemplateKey(): string
    {
        return 'warranty_ending';
    }

    protected function getEmailVariables($notifiable): array
    {
        return [
            'product_name' => $this->warrantyData['product_name'],
            'manufacturer' => $this->warrantyData['manufacturer'],
            'warranty_end_date' => $this->warrantyData['warranty_end_date'] ?? '',
            'days_remaining' => $this->daysRemaining,
            'file_url' => $this->warrantyData['file_url'],
        ];
    }

    protected function getFallbackMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Warranty Ending Soon')
            ->line('A warranty is ending soon.')
            ->line("Product: {$this->warrantyData['product_name']}")
            ->line("Manufacturer: {$this->warrantyData['manufacturer']}")
            ->line("End date: {$this->warrantyData['warranty_end_date']}");

        if ($this->daysRemaining >= 0) {
            $message->line("Days remaining: {$this->daysRemaining}");
        }

        return $message
            ->action('View File', $this->warrantyData['file_url'])
            ->line('Thank you for using PaperPulse!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'warranty_ending',
            'warranty_id' => $this->warrantyData['id'],
            'file_id' => $this->warrantyData['file_id'],
            'product_name' => $this->warrantyData['product_name'],
            'manufacturer' => $this->warrantyData['manufacturer'],
            'warranty_end_date' => $this->warrantyData['warranty_end_date'],
            'days_remaining' => $this->daysRemaining,
            'created_at' => now()->toISOString(),
        ];
    }
}
