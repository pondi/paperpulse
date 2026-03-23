<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Collection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublicCollectionSharedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Collection $collection,
        private readonly User $createdBy,
        private readonly string $url,
        private readonly ?string $password,
        private readonly ?Carbon $expiresAt,
    ) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->createdBy->name.' shared a collection with you')
            ->greeting('Hello!')
            ->line($this->createdBy->name.' has shared a collection of documents with you via PaperPulse.')
            ->line('Collection: '.$this->collection->name);

        if ($this->password) {
            $message->line('Password: '.$this->password);
        }

        if ($this->expiresAt) {
            $message->line('This link expires on '.$this->expiresAt->format('F j, Y \a\t g:i A').'.');
        }

        $message->action('View Collection', $this->url)
            ->line('Thank you for using PaperPulse!');

        return $message;
    }
}
