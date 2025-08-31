<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\FileShare;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentSharedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Document $document;

    protected User $sharedBy;

    protected FileShare $share;

    /**
     * Create a new notification instance.
     */
    public function __construct(Document $document, User $sharedBy, FileShare $share)
    {
        $this->document = $document;
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
        return (new MailMessage)
            ->subject('Document Shared: '.$this->document->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->sharedBy->name.' has shared a document with you.')
            ->line('Document: '.$this->document->title)
            ->line('Permission: '.ucfirst($this->share->permission))
            ->when($this->share->expires_at, function ($message) {
                return $message->line('Expires: '.$this->share->expires_at->format('Y-m-d H:i'));
            })
            ->action('View Document', route('documents.show', $this->document))
            ->line('Thank you for using PaperPulse!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_shared',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'shared_by_id' => $this->sharedBy->id,
            'shared_by_name' => $this->sharedBy->name,
            'permission' => $this->share->permission,
            'expires_at' => $this->share->expires_at?->toISOString(),
        ];
    }
}
