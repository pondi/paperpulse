<?php

namespace App\Http\Resources\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileInertiaResource extends JsonResource
{
    protected bool $detailed = false;

    protected bool $includeDetailsUrl = false;

    public static function forIndex($resource): self
    {
        return new self($resource);
    }

    public static function forShow($resource): self
    {
        $instance = new self($resource);
        $instance->detailed = true;

        return $instance;
    }

    public function withDetailsUrl(): self
    {
        $this->includeDetailsUrl = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $typeFolder = $this->file_type === 'document' ? 'documents' : 'receipts';
        $extension = $this->fileExtension ?? 'pdf';

        $previewUrl = null;
        if ($this->has_image_preview && $this->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $this->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        $data = [
            'id' => $this->id,
            'guid' => $this->guid,
            'name' => $this->fileName,
            'file_type' => $this->file_type,
            'status' => $this->status,
            'uploaded_at' => $this->uploaded_at,
            'extension' => $extension,
            'mime_type' => $this->fileType,
            'has_preview' => (bool) $this->has_image_preview,
            'previewUrl' => $previewUrl,
            'viewUrl' => route('documents.serve', [
                'guid' => $this->guid,
                'type' => $typeFolder,
                'extension' => $extension,
                'variant' => 'original',
            ]),
        ];

        if ($this->includeDetailsUrl) {
            // Route to appropriate show page based on entity type
            $data['detailsUrl'] = $this->getEntityDetailsUrl();
        }

        if ($this->detailed) {
            $hasArchivePdf = ! empty($this->s3_archive_path);
            $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
            $pdfUrl = null;

            if ($hasPdfVariant) {
                $pdfUrl = route('documents.serve', [
                    'guid' => $this->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                    'variant' => $hasArchivePdf ? 'archive' : 'original',
                ]);
            }

            $data = array_merge($data, [
                'url' => route('documents.serve', [
                    'guid' => $this->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $pdfUrl,
                'size' => $this->fileSize,
                'is_pdf' => $hasPdfVariant,
                'file_created_at' => $this->file_created_at,
                'file_modified_at' => $this->file_modified_at,
            ]);
        }

        return $data;
    }

    /**
     * Get the details URL for the file's primary entity.
     */
    protected function getEntityDetailsUrl(): ?string
    {
        $primaryEntity = $this->primaryEntity;

        if (! $primaryEntity?->entity) {
            return null;
        }

        $entityType = $primaryEntity->entity_type;
        $entityId = $primaryEntity->entity_id;

        return match ($entityType) {
            'receipt' => route('receipts.show', $entityId),
            'document' => route('documents.show', $entityId),
            'contract' => route('contracts.show', $entityId),
            'invoice' => route('invoices.show', $entityId),
            'voucher' => route('vouchers.show', $entityId),
            'warranty' => route('warranties.show', $entityId),
            'bank_statement' => route('bank-statements.show', $entityId),
            default => null,
        };
    }
}
