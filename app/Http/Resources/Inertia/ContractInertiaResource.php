<?php

namespace App\Http\Resources\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractInertiaResource extends JsonResource
{
    protected bool $detailed = false;

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

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'contract_title' => $this->contract_title,
            'contract_type' => $this->contract_type,
            'parties' => $this->parties,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'contract_value' => $this->contract_value,
            'currency' => $this->currency,
            'status' => $this->status,
            'summary' => $this->summary,
            'governing_law' => $this->governing_law,
            'jurisdiction' => $this->jurisdiction,
            'file_id' => $this->file_id,
        ];

        if ($this->detailed) {
            $data = array_merge($data, [
                'signature_date' => $this->signature_date?->format('Y-m-d'),
                'duration' => $this->duration,
                'renewal_terms' => $this->renewal_terms,
                'termination_conditions' => $this->termination_conditions,
                'payment_schedule' => $this->payment_schedule,
                'key_terms' => $this->key_terms,
                'obligations' => $this->obligations,
                'file' => $this->buildFileInfo(),
                'tags' => $this->mapTags(),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]);
        }

        return $data;
    }

    private function buildFileInfo(): ?array
    {
        if (! $this->relationLoaded('file') || ! $this->file) {
            return null;
        }

        $extension = $this->file->fileExtension ?? 'pdf';
        $typeFolder = 'documents';
        $hasArchivePdf = ! empty($this->file->s3_archive_path);
        $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
        $pdfUrl = null;

        if ($hasPdfVariant) {
            $pdfUrl = route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => $typeFolder,
                'extension' => 'pdf',
                'variant' => $hasArchivePdf ? 'archive' : 'original',
            ]);
        }

        $previewUrl = null;
        if ($this->file->has_image_preview && $this->file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
            'id' => $this->file->id,
            'url' => route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => $typeFolder,
                'extension' => $extension,
            ]),
            'pdfUrl' => $pdfUrl,
            'previewUrl' => $previewUrl,
            'extension' => $extension,
            'mime_type' => $this->file->mime_type,
            'size' => $this->file->fileSize,
            'guid' => $this->file->guid,
            'has_preview' => $this->file->has_image_preview,
            'is_pdf' => $hasPdfVariant,
            'uploaded_at' => $this->file->uploaded_at,
            'file_created_at' => $this->file->file_created_at,
            'file_modified_at' => $this->file->file_modified_at,
        ];
    }

    private function mapTags(): array
    {
        if (! $this->relationLoaded('tags')) {
            return [];
        }

        return $this->tags->map(fn ($tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
        ])->values()->all();
    }
}
