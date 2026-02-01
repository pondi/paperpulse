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

        // Get the primary entity for linking
        $primaryEntity = $existingFile->primaryEntity;
        $entity = $primaryEntity?->entity;
        $entityType = $primaryEntity?->entity_type;

        $this->existingFileData['entity_type'] = $entityType;
        $this->existingFileData['entity_id'] = $entity?->id;
        $this->existingFileData['entity_title'] = match ($entityType) {
            'receipt' => $entity?->merchant?->name,
            'document' => $entity?->title,
            'contract' => $entity?->contract_title ?? $entity?->title,
            'invoice' => $entity?->vendor_name ?? $entity?->from_name,
            default => null,
        };
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

        // Add link to the existing entity
        $entityType = $this->existingFileData['entity_type'];
        $entityId = $this->existingFileData['entity_id'];

        if ($entityType && $entityId) {
            $variables['file_url'] = $this->getEntityRoute($entityType, $entityId);
            $variables['file_link_text'] = 'View '.ucfirst($entityType);
        } else {
            $variables['file_url'] = route('files.index');
            $variables['file_link_text'] = 'View Files';
        }

        return $variables;
    }

    /**
     * Get the route for an entity type
     */
    protected function getEntityRoute(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'receipt' => route('receipts.show', $entityId),
            'document' => route('documents.show', $entityId),
            'contract' => route('contracts.show', $entityId),
            'invoice' => route('invoices.show', $entityId),
            'voucher' => route('vouchers.show', $entityId),
            'warranty' => route('warranties.show', $entityId),
            'bank_statement' => route('bank-statements.show', $entityId),
            default => route('files.index'),
        };
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

        // Add action button to view the existing entity
        $entityType = $this->existingFileData['entity_type'];
        $entityId = $this->existingFileData['entity_id'];

        if ($entityType && $entityId) {
            $mailMessage->action('View '.ucfirst($entityType), $this->getEntityRoute($entityType, $entityId));
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
            'entity_type' => $this->existingFileData['entity_type'],
            'entity_id' => $this->existingFileData['entity_id'],
            'entity_title' => $this->existingFileData['entity_title'],
            'uploaded_at' => $this->existingFileData['uploadedAt'],
            'created_at' => now()->toISOString(),
        ];
    }
}
