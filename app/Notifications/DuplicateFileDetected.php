<?php

namespace App\Notifications;

use App\Models\File;
use Illuminate\Notifications\Messages\MailMessage;

class DuplicateFileDetected extends TemplatedNotification
{
    protected $uploadedFileName;

    protected $existingFileData;

    protected $fileHash;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $uploadedFileName, File $existingFile, string $fileHash)
    {
        $this->uploadedFileName = $uploadedFileName;
        $this->fileHash = $fileHash;

        // Store only the data we need from the existing file
        $this->existingFileData = [
            'id' => $existingFile->id,
            'guid' => $existingFile->guid,
            'fileName' => $existingFile->fileName,
            'fileType' => $existingFile->file_type,
            'uploadedAt' => $existingFile->uploaded_at?->toISOString(),
        ];

        // Get the associated receipt or document for linking
        $receipt = $existingFile->receipts()->first();
        $document = $existingFile->documents()->first();

        $this->existingFileData['receipt_id'] = $receipt?->id;
        $this->existingFileData['document_id'] = $document?->id;
        $this->existingFileData['merchant_name'] = $receipt?->merchant?->name;
        $this->existingFileData['document_title'] = $document?->title;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        // Add email notification if user has preference enabled
        if ($notifiable->preference('email_notify_duplicate_detected')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the email template key for this notification
     */
    protected function getEmailTemplateKey(): string
    {
        return 'duplicate_file_detected';
    }

    /**
     * Get the variables to pass to the email template
     */
    protected function getEmailVariables($notifiable): array
    {
        $variables = [
            'uploaded_file_name' => $this->uploadedFileName,
            'existing_file_name' => $this->existingFileData['fileName'],
            'file_type' => $this->existingFileData['fileType'],
        ];

        // Add link to the existing file's receipt or document
        if ($this->existingFileData['receipt_id']) {
            $variables['file_url'] = route('receipts.show', $this->existingFileData['receipt_id']);
            $variables['file_link_text'] = 'View Receipt';
        } elseif ($this->existingFileData['document_id']) {
            $variables['file_url'] = route('documents.show', $this->existingFileData['document_id']);
            $variables['file_link_text'] = 'View Document';
        } else {
            $variables['file_url'] = route('files.index');
            $variables['file_link_text'] = 'View Files';
        }

        return $variables;
    }

    /**
     * Get fallback mail message if template is not found
     */
    protected function getFallbackMail($notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Duplicate File Detected')
            ->greeting('Hello!')
            ->line("The file \"{$this->uploadedFileName}\" was not uploaded because it's a duplicate.")
            ->line("An identical file \"{$this->existingFileData['fileName']}\" already exists in your account.");

        // Add action button to view the existing file
        if ($this->existingFileData['receipt_id']) {
            $mailMessage->action('View Receipt', route('receipts.show', $this->existingFileData['receipt_id']));
        } elseif ($this->existingFileData['document_id']) {
            $mailMessage->action('View Document', route('documents.show', $this->existingFileData['document_id']));
        } else {
            $mailMessage->action('View Files', route('files.index'));
        }

        return $mailMessage->line('No duplicate files were created.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'duplicate_file_detected',
            'uploaded_file_name' => $this->uploadedFileName,
            'existing_file_id' => $this->existingFileData['id'],
            'existing_file_guid' => $this->existingFileData['guid'],
            'existing_file_name' => $this->existingFileData['fileName'],
            'file_type' => $this->existingFileData['fileType'],
            'file_hash' => $this->fileHash,
            'receipt_id' => $this->existingFileData['receipt_id'],
            'document_id' => $this->existingFileData['document_id'],
            'merchant_name' => $this->existingFileData['merchant_name'],
            'document_title' => $this->existingFileData['document_title'],
            'uploaded_at' => $this->existingFileData['uploadedAt'],
            'created_at' => now()->toISOString(),
        ];
    }
}
