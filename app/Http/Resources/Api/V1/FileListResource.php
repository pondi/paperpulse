<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Receipt;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class FileListResource extends JsonResource
{
    public function toArray($request): array
    {
        $primaryEntity = $this->relationLoaded('primaryEntity') ? $this->primaryEntity : null;
        $entity = $primaryEntity?->entity;
        $entityType = $primaryEntity?->entity_type;

        $extension = $this->fileExtension ?: pathinfo((string) $this->original_filename, PATHINFO_EXTENSION);
        $hasArchivePdf = ! empty($this->s3_archive_path) || strtolower((string) $extension) === 'pdf';

        $title = $this->buildTitle($entity, $entityType);
        $snippet = $this->buildSnippet($entity, $entityType);
        $primaryDate = $this->buildPrimaryDate($entity, $entityType);

        $fileId = $this->id;
        $hasPreview = (bool) $this->has_image_preview;

        return [
            'id' => $fileId,
            'guid' => $this->guid,
            'checksum_sha256' => $this->file_hash,
            'file_type' => $this->file_type,
            'processing_type' => $this->processing_type,
            'status' => $this->status,

            'name' => $this->fileName ?? $this->original_filename,
            'extension' => $this->fileExtension,
            'mime_type' => $this->fileType ?? $this->mime_type,
            'size' => $this->fileSize ?? $this->file_size,
            'uploaded_at' => $this->uploaded_at,

            'has_image_preview' => $hasPreview,
            'has_archive_pdf' => $hasArchivePdf,

            'title' => $title,
            'snippet' => $snippet,
            'date' => $primaryDate,

            'total' => $entity instanceof Receipt ? $entity->total_amount : ($entity instanceof Invoice ? $entity->total_amount : null),
            'currency' => $entity instanceof Receipt ? $entity->currency : ($entity instanceof Invoice ? $entity->currency : null),
            'document_type' => $entity instanceof Document ? $entity->document_type : null,
            'page_count' => $entity instanceof Document ? $entity->page_count : null,

            'entity' => $entity ? $this->buildEntityData($entity, $entityType) : null,

            'links' => [
                'content' => route('api.files.content', ['file' => $fileId]),
                'preview' => $hasPreview ? route('api.files.content', ['file' => $fileId]).'?variant=preview' : null,
                'pdf' => $hasArchivePdf ? route('api.files.content', ['file' => $fileId]).'?variant=archive' : null,
            ],
        ];
    }

    private function buildEntityData($entity, ?string $entityType): array
    {
        $data = [
            'id' => $entity->id,
            'type' => $entityType,
        ];

        if ($entity instanceof Receipt) {
            $data['merchant'] = $entity->relationLoaded('merchant') && $entity->merchant ? [
                'id' => $entity->merchant->id,
                'name' => $entity->merchant->name,
            ] : null;
            $data['category'] = $entity->relationLoaded('category') && $entity->category ? [
                'id' => $entity->category->id,
                'name' => $entity->category->name,
                'color' => $entity->category->color,
            ] : null;
        } elseif ($entity instanceof Document) {
            $data['title'] = $entity->title;
            $data['category'] = $entity->relationLoaded('category') && $entity->category ? [
                'id' => $entity->category->id,
                'name' => $entity->category->name,
                'color' => $entity->category->color,
            ] : null;
        } elseif ($entity instanceof Contract) {
            $data['title'] = $entity->contract_title ?? $entity->title;
        } elseif ($entity instanceof Invoice) {
            $data['vendor_name'] = $entity->vendor_name ?? $entity->from_name;
            $data['invoice_number'] = $entity->invoice_number;
        }

        return $data;
    }

    private function buildTitle($entity, ?string $entityType): ?string
    {
        if ($entity instanceof Receipt) {
            $merchantName = $entity->merchant?->name;
            if (! empty($merchantName)) {
                return $merchantName;
            }
        }

        if ($entity instanceof Document) {
            if (! empty($entity->title)) {
                return $entity->title;
            }
        }

        if ($entity instanceof Contract) {
            return $entity->contract_title ?? $entity->title ?? $this->fileName;
        }

        if ($entity instanceof Invoice) {
            return 'Invoice from '.($entity->vendor_name ?? $entity->from_name ?? 'Unknown');
        }

        return $this->fileName ?? $this->original_filename;
    }

    private function buildSnippet($entity, ?string $entityType): ?string
    {
        $value = null;

        if ($entity instanceof Receipt) {
            $value = $entity->summary ?? $entity->receipt_description ?? $entity->note;
        } elseif ($entity instanceof Document) {
            $value = $entity->summary ?? $entity->description ?? $entity->note;
        } elseif ($entity instanceof Contract) {
            $value = $entity->summary ?? $entity->notes;
        } elseif ($entity instanceof Invoice) {
            $value = $entity->notes;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), 160);
    }

    private function buildPrimaryDate($entity, ?string $entityType): mixed
    {
        if ($entity instanceof Receipt) {
            return $entity->receipt_date ?? $this->uploaded_at ?? $this->created_at;
        }

        if ($entity instanceof Document) {
            return $entity->document_date ?? $this->uploaded_at ?? $this->created_at;
        }

        if ($entity instanceof Contract) {
            return $entity->effective_date ?? $entity->created_at ?? $this->uploaded_at;
        }

        if ($entity instanceof Invoice) {
            return $entity->invoice_date ?? $entity->created_at ?? $this->uploaded_at;
        }

        return $this->uploaded_at ?? $this->created_at;
    }
}
