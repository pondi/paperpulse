<?php

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Notifications\Messages\MailMessage;

class VoucherExpiringNotification extends TemplatedNotification
{
    protected array $voucherData;

    protected int $daysRemaining;

    public function __construct(Voucher $voucher, int $daysRemaining)
    {
        $this->voucherData = [
            'id' => $voucher->id,
            'file_id' => $voucher->file_id,
            'code' => $voucher->code,
            'merchant_name' => $voucher->merchant?->name ?? 'Unknown',
            'expiry_date' => $voucher->expiry_date?->toDateString(),
            'current_value' => $voucher->current_value,
            'currency' => $voucher->currency ?? 'NOK',
            'voucher_url' => route('vouchers.show', $voucher->id),
        ];
        $this->daysRemaining = $daysRemaining;
    }

    public function via($notifiable): array
    {
        $channels = [];

        if ($notifiable->preference('notify_voucher_expiring', true)) {
            $channels[] = 'database';
        }

        if ($notifiable->preference('email_notify_voucher_expiring', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    protected function getEmailTemplateKey(): string
    {
        return 'voucher_expiring';
    }

    protected function getEmailVariables($notifiable): array
    {
        return [
            'voucher_code' => $this->voucherData['code'] ?? '',
            'merchant_name' => $this->voucherData['merchant_name'],
            'expiry_date' => $this->voucherData['expiry_date'] ?? '',
            'days_remaining' => $this->daysRemaining,
            'current_value' => $this->voucherData['current_value'],
            'currency' => $this->voucherData['currency'],
            'voucher_url' => $this->voucherData['voucher_url'],
        ];
    }

    protected function getFallbackMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Voucher Expiring Soon')
            ->line('One of your vouchers is expiring soon.')
            ->line("Merchant: {$this->voucherData['merchant_name']}")
            ->line("Expiry date: {$this->voucherData['expiry_date']}");

        if ($this->daysRemaining >= 0) {
            $message->line("Days remaining: {$this->daysRemaining}");
        }

        if (! empty($this->voucherData['code'])) {
            $message->line("Voucher code: {$this->voucherData['code']}");
        }

        return $message
            ->action('View Voucher', $this->voucherData['voucher_url'])
            ->line('Thank you for using PaperPulse!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'voucher_expiring',
            'voucher_id' => $this->voucherData['id'],
            'file_id' => $this->voucherData['file_id'],
            'voucher_code' => $this->voucherData['code'],
            'merchant_name' => $this->voucherData['merchant_name'],
            'expiry_date' => $this->voucherData['expiry_date'],
            'days_remaining' => $this->daysRemaining,
            'current_value' => $this->voucherData['current_value'],
            'currency' => $this->voucherData['currency'],
            'created_at' => now()->toISOString(),
        ];
    }
}
