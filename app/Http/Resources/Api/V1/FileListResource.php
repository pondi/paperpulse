<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Document;
use App\Models\Receipt;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class FileListResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Receipt|null $receipt */
        $receipt = $this->relationLoaded('primaryReceipt') ? $this->primaryReceipt : null;

        /** @var Document|null $document */
        $document = $this->relationLoaded('primaryDocument') ? $this->primaryDocument : null;

        $extension = $this->fileExtension ?: pathinfo((string) $this->original_filename, PATHINFO_EXTENSION);
        $hasArchivePdf = ! empty($this->s3_archive_path) || strtolower((string) $extension) === 'pdf';

        $title = $this->buildTitle($receipt, $document);
        $snippet = $this->buildSnippet($receipt, $document);
        $primaryDate = $this->buildPrimaryDate($receipt, $document);

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

            'total' => $receipt?->total_amount,
            'currency' => $receipt?->currency,
            'document_type' => $document?->document_type,
            'page_count' => $document?->page_count,

            'receipt' => $receipt ? [
                'id' => $receipt->id,
                'merchant' => $receipt->relationLoaded('merchant') && $receipt->merchant ? [
                    'id' => $receipt->merchant->id,
                    'name' => $receipt->merchant->name,
                ] : null,
                'category' => $receipt->relationLoaded('category') && $receipt->category ? [
                    'id' => $receipt->category->id,
                    'name' => $receipt->category->name,
                    'color' => $receipt->category->color,
                ] : null,
            ] : null,

            'document' => $document ? [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->relationLoaded('category') && $document->category ? [
                    'id' => $document->category->id,
                    'name' => $document->category->name,
                    'color' => $document->category->color,
                ] : null,
            ] : null,

            'links' => [
                'content' => route('api.files.content', ['file' => $fileId]),
                'preview' => $hasPreview ? route('api.files.content', ['file' => $fileId]).'?variant=preview' : null,
                'pdf' => $hasArchivePdf ? route('api.files.content', ['file' => $fileId]).'?variant=archive' : null,
            ],
        ];
    }

    private function buildTitle(?Receipt $receipt, ?Document $document): ?string
    {
        if ($receipt) {
            $merchantName = $receipt->merchant?->name;
            if (! empty($merchantName)) {
                return $merchantName;
            }
        }

        if ($document) {
            if (! empty($document->title)) {
                return $document->title;
            }
        }

        return $this->fileName ?? $this->original_filename;
    }

    private function buildSnippet(?Receipt $receipt, ?Document $document): ?string
    {
        $value = $receipt?->summary
            ?? $receipt?->receipt_description
            ?? $receipt?->note
            ?? $document?->summary
            ?? $document?->description
            ?? $document?->note;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), 160);
    }

    private function buildPrimaryDate(?Receipt $receipt, ?Document $document): mixed
    {
        return $receipt?->receipt_date
            ?? $document?->document_date
            ?? $this->uploaded_at
            ?? $this->created_at;
    }
}
