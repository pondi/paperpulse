<?php

namespace App\Http\Resources\Inertia;

use App\Models\File;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin File */
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
        $typeFolder = $this->file_type === 'receipt' ? 'receipts' : 'documents';
        $extension = $this->fileExtension ?? 'pdf';

        $previewUrl = null;
        if (StoragePathBuilder::variantPath($this->resource, 'preview') !== null) {
            $previewUrl = route('documents.serve', [
                'guid' => $this->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        $data = [
            'id' => $this->id,
            'guid' => $this->guid,
            'name' => $this->displayTitle(),
            'original_name' => $this->fileName,
            'file_type' => $this->file_type,
            'status' => $this->status,
            'uploaded_at' => $this->uploaded_at,
            'extension' => $extension,
            'mime_type' => $this->fileType,
            'has_preview' => StoragePathBuilder::variantPath($this->resource, 'preview') !== null,
            'previewUrl' => $previewUrl,
            'viewUrl' => route('documents.serve', [
                'guid' => $this->guid,
                'type' => $typeFolder,
                'extension' => $extension,
                'variant' => 'original',
            ]),
        ];

        if ($this->relationLoaded('processingJobs')) {
            $data['processing'] = $this->resource->processingSummary();
        }

        if ($this->status === 'needs_review') {
            $review = $this->meta['review'] ?? [];
            $data['review'] = array_intersect_key($review, array_flip(['reason', 'confidence', 'reasoning', 'page_limit', 'text_limit_bytes']));
        }

        if ($this->includeDetailsUrl) {
            $data['detailsUrl'] = route('files.show', $this->id);
        }

        if ($this->detailed) {
            $pdfVariant = StoragePathBuilder::pdfVariant($this->resource);
            $hasPdfVariant = $pdfVariant !== null;
            $pdfUrl = null;

            if ($hasPdfVariant) {
                $pdfUrl = route('documents.serve', [
                    'guid' => $this->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                    'variant' => $pdfVariant,
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

    private function displayTitle(): string
    {
        $entity = null;
        if ($this->relationLoaded('primaryEntity') && $this->primaryEntity?->relationLoaded('entity')) {
            $entity = $this->primaryEntity->entity;
        } elseif ($this->relationLoaded('extractableEntities')) {
            $extraction = $this->extractableEntities->firstWhere('is_primary', true);
            $entity = $extraction?->relationLoaded('entity') ? $extraction->entity : null;
        }
        $title = $entity?->getAttribute('title') ?? $entity?->getAttribute('contract_title')
            ?? ($this->organization_summary['title'] ?? null)
            ?? data_get($this->meta, 'gemini.entities.0.data.metadata.title');
        if (! is_string($title) || trim($title) === '' || in_array(mb_strtolower(trim($title)), ['document', 'detected document', 'receipt', 'invoice', 'contract', 'file'], true)) {
            return $this->fileName;
        }

        return trim($title);
    }
}
