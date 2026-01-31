<?php

namespace App\Http\Resources\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherInertiaResource extends JsonResource
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
            'voucher_type' => $this->voucher_type,
            'code' => $this->code,
            'merchant' => $this->relationLoaded('merchant') && $this->merchant ? [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
            ] : null,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'original_value' => $this->original_value,
            'current_value' => $this->current_value,
            'currency' => $this->currency,
            'installment_count' => $this->installment_count,
            'monthly_payment' => $this->monthly_payment,
            'first_payment_date' => $this->first_payment_date?->format('Y-m-d'),
            'final_payment_date' => $this->final_payment_date?->format('Y-m-d'),
            'terms_and_conditions' => $this->terms_and_conditions,
            'restrictions' => $this->restrictions,
            'is_redeemed' => $this->is_redeemed,
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'file_id' => $this->file_id,
        ];

        if ($this->detailed) {
            $data = array_merge($data, [
                'barcode' => $this->barcode,
                'qr_code' => $this->qr_code,
                'redemption_location' => $this->redemption_location,
                'file' => $this->buildFileInfo(),
                'tags' => $this->mapTags(),
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
            'uploaded_at' => $this->file->uploaded_at?->toIso8601String(),
            'file_created_at' => $this->file->file_created_at?->toIso8601String(),
            'file_modified_at' => $this->file->file_modified_at?->toIso8601String(),
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
