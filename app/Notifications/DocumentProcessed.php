<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $fileId, public string $title) {}

    public static function forFile(File $file): self
    {
        return new self($file->id, $file->organization_summary['title'] ?? $file->fileName);
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $channels = $notifiable->preference('notify_processing_complete', true) ? ['database', 'broadcast'] : [];
        if ($notifiable->preference('email_notify_processing_complete', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Document processed')->line($this->title.' has been processed.')
            ->action('View document', route('files.show', $this->fileId));
    }

    public function toArray(User $notifiable): array
    {
        return ['type' => 'document_processed', 'file_id' => $this->fileId, 'document_title' => $this->title, 'success' => true];
    }
}
