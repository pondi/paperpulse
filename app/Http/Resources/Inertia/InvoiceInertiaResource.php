<?php

namespace App\Http\Resources\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceInertiaResource extends JsonResource
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
        $lineItemsCount = $this->line_items_count ?? ($this->relationLoaded('lineItems') ? $this->lineItems->count() : 0);

        $data = [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type,
            'from_name' => $this->from_name,
            'to_name' => $this->to_name,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'total_amount' => $this->total_amount,
            'amount_due' => $this->amount_due,
            'currency' => $this->currency,
            'payment_status' => $this->payment_status,
            'payment_terms' => $this->payment_terms,
            'line_items_count' => $lineItemsCount,
            'file_id' => $this->file_id,
        ];

        if ($this->detailed) {
            $data = array_merge($data, [
                'from_address' => $this->from_address,
                'from_vat_number' => $this->from_vat_number,
                'from_email' => $this->from_email,
                'from_phone' => $this->from_phone,
                'to_address' => $this->to_address,
                'to_vat_number' => $this->to_vat_number,
                'to_email' => $this->to_email,
                'to_phone' => $this->to_phone,
                'delivery_date' => $this->delivery_date?->format('Y-m-d'),
                'subtotal' => $this->subtotal,
                'tax_amount' => $this->tax_amount,
                'discount_amount' => $this->discount_amount,
                'shipping_amount' => $this->shipping_amount,
                'amount_paid' => $this->amount_paid,
                'payment_method' => $this->payment_method,
                'purchase_order_number' => $this->purchase_order_number,
                'reference_number' => $this->reference_number,
                'notes' => $this->notes,
                'line_items' => $this->mapLineItems(),
                'file' => $this->buildFileInfo(),
                'tags' => $this->mapTags(),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
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

    private function mapLineItems(): array
    {
        if (! $this->relationLoaded('lineItems')) {
            return [];
        }

        return $this->lineItems->map(fn ($item) => [
            'id' => $item->id,
            'line_number' => $item->line_number,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'tax_amount' => $item->tax_amount,
            'total_amount' => $item->total_amount,
        ])->values()->all();
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
